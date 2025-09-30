#!/usr/bin/env python3
"""
wp_gitops_agent.py
Continuous, surgical reconciler for WordPress files from a Git-synced repo.

Design:
- Expects git-sync to publish the current commit at a stable symlink, e.g. /git/repo
  (--root=/git/root --link=/git/repo). The agent detects updates by reading the
  symlink target (its basename is the commit hash) and reconciles on change.
- Only manages:
  * DOCROOT/wp-config.php
  * DOCROOT/.htaccess
  * DOCROOT/wp-content/mu-plugins/ (exact mirror of repo/plugins/<slugs in enabled.txt>)
- Supports "strict" drift correction with ALWAYS_ENFORCE=1 to verify/fix every loop.
- Copy-if-different to avoid churn; ownership/mode updated only when needed.

Env (with defaults):
  REPO_LINK=/git/repo
  REPO_WORDPRESS=wordpress
  REPO_PLUGINS=plugins
  DOCROOT=/var/www/html
  DOC_UID=33, DOC_GID=33
  MU_UID=0,  MU_GID=0
  CORE_MODE=0o644, MU_MODE=0o555
  LOOP_SECONDS=10
  ALWAYS_ENFORCE=(0/1)
"""

import os
import time
import shutil
import hashlib
import stat
from pathlib import Path

# -------- Config via env --------
REPO_LINK       = Path(os.getenv("REPO_LINK", "/git/repo"))             # git-sync --link points here
REPO_WORDPRESS  = Path(os.getenv("REPO_WORDPRESS", "wordpress"))        # relative to repo root
REPO_PLUGINS    = Path(os.getenv("REPO_PLUGINS", "plugins"))            # relative to repo root

DOCROOT         = Path(os.getenv("DOCROOT", "/var/www/html"))
MU_DIR          = DOCROOT / "wp-content" / "mu-plugins"

ENABLED_FILE    = REPO_PLUGINS / "enabled.txt"
PROXY_LOADER    = REPO_PLUGINS / "proxy-loader.php"

# Ownership / perms
DOC_UID         = int(os.getenv("DOC_ID", "33"))   # www-data (Debian/Ubuntu)
DOC_GID         = int(os.getenv("DOC_ID", "33"))
MU_UID          = int(os.getenv("MU_ID", "0"))     # root:root to lock mu-plugins
MU_GID          = int(os.getenv("MU_ID", "0"))
# Modes are parsed as octal strings (e.g., "0o644")
CORE_MODE       = int(os.getenv("CORE_MODE", "0o644"), 8)
MU_MODE         = int(os.getenv("MU_MODE", "0o555"), 8)

LOOP_SECONDS    = int(os.getenv("LOOP_SECONDS", "10"))
ALWAYS_ENFORCE  = os.getenv("ALWAYS_ENFORCE", "0").lower() in ("1", "true", "yes", "on")
LOG_PREFIX      = os.getenv("LOG_PREFIX", "[wp-gitops]")

# Only these two top-level files are managed from repo/wordpress
CORE_ALLOWED = {"wp-config.php", ".htaccess"}

def log(msg: str) -> None:
    print(f"{LOG_PREFIX} {msg}", flush=True)

# -------- Utility functions --------

def sha256_file(p: Path) -> str:
    """Compute sha256 of a file in streaming fashion."""
    h = hashlib.sha256()
    with open(p, "rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()

def safe_copy_file(src: Path, dst: Path, uid: int, gid: int, mode: int) -> None:
    """Atomic copy with metadata set before swap."""
    dst.parent.mkdir(parents=True, exist_ok=True)
    tmp = dst.with_suffix(dst.suffix + ".tmp.copy")
    shutil.copy2(src, tmp)
    os.chown(tmp, uid, gid)
    os.chmod(tmp, mode)
    tmp.replace(dst)  # atomic replace
    log(f"applied {src} -> {dst}")

def ensure_metadata(p: Path, uid: int, gid: int, mode: int | None = None) -> None:
    """Apply uid/gid/mode only if different."""
    try:
        st = p.lstat()
        changed = False
        if (st.st_uid != uid) or (st.st_gid != gid):
            os.chown(p, uid, gid)
            changed = True
        if mode is not None:
            cur = stat.S_IMODE(st.st_mode)
            if cur != mode:
                os.chmod(p, mode)
                changed = True
        if changed:
            log(f"metadata fixed: {p}")
    except FileNotFoundError:
        pass

def files_differ(src: Path, dst: Path) -> bool:
    """Return True if dst missing or content differs (symlinks compare targets)."""
    if not dst.exists():
        return True
    # Symlink compare
    if src.is_symlink() or dst.is_symlink():
        try:
            return os.readlink(src) != os.readlink(dst)
        except OSError:
            return True
    # File compare
    if src.is_file() and dst.is_file():
        try:
            if src.stat().st_size != dst.stat().st_size:
                return True
        except FileNotFoundError:
            return True
        # Same size -> hash compare
        return sha256_file(src) != sha256_file(dst)
    # Type mismatch (file vs dir)
    return (src.is_dir() != dst.is_dir())

def copy_if_different_file(src: Path, dst: Path, uid: int, gid: int, mode: int) -> None:
    """Atomic copy only when content differs; otherwise ensure metadata."""
    if files_differ(src, dst):
        safe_copy_file(src, dst, uid, gid, mode)
    else:
        ensure_metadata(dst, uid, gid, mode)

def mirror_tree(src: Path, dst: Path, uid: int, gid: int, file_mode: int, dir_mode: int) -> None:
    """
    Make dst exactly match src (content + perms) with minimal I/O:
      - Copy/update only changed files/symlinks
      - Recurse into dirs
      - Remove entries in dst that don't exist in src
      - Apply uid/gid/mode only when needed
    """
    dst.mkdir(parents=True, exist_ok=True)
    ensure_metadata(dst, uid, gid, dir_mode)

    src_entries = {p.name: p for p in src.iterdir()} if src.exists() else {}
    dst_entries = {p.name: p for p in dst.iterdir()} if dst.exists() else {}

    # Add/update
    for name, s in src_entries.items():
        d = dst / name
        if s.is_symlink():
            target = os.readlink(s)
            if d.exists() or d.is_symlink():
                try:
                    if os.readlink(d) != target:
                        d.unlink()
                        os.symlink(target, d)
                        log(f"symlink updated: {d} -> {target}")
                except OSError:
                    if d.is_dir():
                        shutil.rmtree(d, ignore_errors=True)
                    else:
                        d.unlink(missing_ok=True)
                    os.symlink(target, d)
                    log(f"symlink replaced: {d} -> {target}")
            else:
                os.symlink(target, d)
                log(f"symlink created: {d} -> {target}")
            # Skipping chmod/chown for symlinks (commonly ignored)
        elif s.is_file():
            copy_if_different_file(s, d, uid, gid, file_mode)
        elif s.is_dir():
            if d.exists() and d.is_file():
                d.unlink()
            mirror_tree(s, d, uid, gid, file_mode, dir_mode)

    # Remove extraneous
    for name, d in dst_entries.items():
        if name not in src_entries:
            if d.is_dir() and not d.is_symlink():
                shutil.rmtree(d, ignore_errors=True)
            else:
                d.unlink(missing_ok=True)
            log(f"removed extraneous: {d}")

# -------- Reconcile routines --------

def ensure_core_files(repo_root: Path) -> None:
    """Enforce only the two allowed top-level files from repo/wordpress -> DOCROOT."""
    src_dir = repo_root / REPO_WORDPRESS
    if not src_dir.exists():
        log(f"repo path missing: {src_dir} (skip core reconcile)")
        return

    for name in CORE_ALLOWED:
        s = src_dir / name
        d = DOCROOT / name
        if not s.exists():
            log(f"warning: {s} not in repo; skipping")
            continue
        copy_if_different_file(s, d, DOC_UID, DOC_GID, CORE_MODE)

def parse_enabled(enabled_path: Path) -> list[str]:
    names: list[str] = []
    if not enabled_path.exists():
        log(f"enabled.txt not found at {enabled_path}, treating as empty set")
        return names
    for line in enabled_path.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        names.append(line)
    return names

def sync_mu_plugins(repo_root: Path) -> None:
    """Mirror only enabled repo plugins into mu-plugins with copy-if-different."""
    MU_DIR.mkdir(parents=True, exist_ok=True)

    enabled = set(parse_enabled(repo_root / ENABLED_FILE))
    desired = set(enabled)

    # Install/update enabled plugins with copy-if-different + exact mirroring
    for slug in sorted(desired):
        src = repo_root / REPO_PLUGINS / slug
        if not src.exists():
            log(f"warning: plugin '{slug}' not found in repo at {src}")
            continue

        dst = MU_DIR / slug
        if src.is_file():
            copy_if_different_file(src, dst, MU_UID, MU_GID, MU_MODE)
        elif src.is_dir():
            mirror_tree(src, dst, MU_UID, MU_GID, file_mode=MU_MODE, dir_mode=MU_MODE)
        else:
            log(f"warning: '{slug}' is neither file nor directory; skipped")

        log(f"mu-plugin reconciled: {slug}")

    # Remove plugins that are present but not enabled (keep proxy-loader separate)
    for child in MU_DIR.iterdir():
        if child.name == "proxy-loader.php":
            continue
        if child.name not in desired:
            if child.is_dir() and not child.is_symlink():
                shutil.rmtree(child, ignore_errors=True)
            else:
                child.unlink(missing_ok=True)
            log(f"mu-plugin removed (disabled): {child.name}")

    # Ensure proxy-loader (copy-if-different)
    proxy_src = repo_root / PROXY_LOADER
    if proxy_src.exists():
        proxy_dst = MU_DIR / "proxy-loader.php"
        copy_if_different_file(proxy_src, proxy_dst, MU_UID, MU_GID, MU_MODE)
    else:
        log(f"warning: proxy-loader not found at {proxy_src}; skipped")

def read_repo_revision(repo_link: Path) -> str:
    """
    Primary: if repo_link is a symlink (git-sync --link), return basename(target) == commit SHA.
    Fallback: hash a small fingerprint of important files so we still detect changes.
    """
    try:
        if repo_link.is_symlink():
            target = os.readlink(repo_link)
            return os.path.basename(target)
        # fallback fingerprint
        fp = []
        for rel in [REPO_WORDPRESS / "wp-config.php",
                    REPO_WORDPRESS / ".htaccess",
                    ENABLED_FILE, PROXY_LOADER]:
            p = repo_link / rel
            if p.exists() and p.is_file():
                fp.append(sha256_file(p))
        return hashlib.sha256("".join(fp).encode()).hexdigest()
    except Exception as e:
        log(f"rev detection error: {e}")
        return "unknown"

def reconcile(repo_link: Path) -> None:
    """One full reconcile pass."""
    if not repo_link.exists():
        log(f"repo link {repo_link} not present yet; waiting...")
        return

    repo_root = repo_link.resolve()  # resolves to the current published worktree
    ensure_core_files(repo_root)
    sync_mu_plugins(repo_root)
    log("reconcile complete")

# -------- Main loop --------

def main() -> None:
    last_rev = None
    log(f"starting agent. docroot={DOCROOT} repo_link={REPO_LINK} always_enforce={ALWAYS_ENFORCE}")
    while True:
        try:
            rev = read_repo_revision(REPO_LINK)
            if ALWAYS_ENFORCE or rev != last_rev:
                if rev != last_rev:
                    log(f"detected repo change: {last_rev} -> {rev}")
                reconcile(REPO_LINK)
                last_rev = rev
        except Exception as e:
            log(f"reconcile error: {e}")
        time.sleep(LOOP_SECONDS)

if __name__ == "__main__":
    main()
