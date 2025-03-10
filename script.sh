#!/bin/sh

cd $HOME/gitops && # ensure we are in the correct directory
echo "2. Starting script via git repo..." && 

# Ops for the entire WordPress directory
echo "3. Starting WordPress directory ops..."

# Delete files in the WordPress directory
for file in "$HOME/gitops/wordpress/"*; do
    target_file="/var/www/html/$(basename "$file")"

    if [ -e "$target_file" ]; then
        if [ -f "$target_file" ]; then
            echo "[-] > Deleted $target_file"
            rm -f "$target_file"  # Remove only files, not directories
        fi

        echo "[+] > Copying $file to $target_file"
        cp -r "$file" "/var/www/html/"
        chmod -R 644 "/var/www/html/$(basename "$file")"
        echo "[*] > Permissions set for $target_file"
    else
        # If the target file doesn't exist, just copy it
        echo "[+] > Copying $file to $target_file"
        cp -r "$file" "/var/www/html/"
        chmod -R 644 "$target_file"
        echo "[*] > Permissions set for $target_file"
    fi
done

mkdir -p "/var/www/html/wp-content/mu-plugins/" && # Ensure mu-plugins exists

echo "4. Setting file/folder permissions..." && 
# Set file permissions (primarily a post-restore step); intentionally done before plugins so they have their own permissions
find "/var/www/html/" -type d -exec chmod 755 {} \; && 
find "/var/www/html/" -type f -exec chmod 644 {} \; && 
chmod 777 -R "/var/www/html/wp-content/" && # wp-content can be modified by anyone

# Plugin ops
echo "5. Starting plugin ops..." && 
if [ -f "$HOME/gitops/plugins/enabled.txt" ]; then 
  while IFS= read -r f || [ -n "$f" ]; do # IFS= sets whitespace to newline. || [ -n "$f" ] prevents the last line from being ignored.
    if [ -d "/var/www/html/wp-content/mu-plugins/$f" ]; then 
      echo "[-] > Deleted /var/www/html/wp-content/mu-plugins/$f" && rm -rf "/var/www/html/wp-content/mu-plugins/$f"; 
    fi; 
    if [ -d "$HOME/gitops/plugins/$f" ]; then 
      echo "[+] > Copied plugins/$f to /var/www/html/wp-content/mu-plugins/$f" && cp -r "$HOME/gitops/plugins/$f" "/var/www/html/wp-content/mu-plugins/$f" && 
      chown -R 0:0 "/var/www/html/wp-content/mu-plugins/$f" && 
      chmod -R 555 "/var/www/html/wp-content/mu-plugins/$f" && 
      echo "[*] > Permissions set for /var/www/html/wp-content/mu-plugins/$f";
    fi; 
  done < "$HOME/gitops/plugins/enabled.txt"; 

  # Load the proxy loader, responsible for loading all 'must-use' plugins
  echo "[+] > Copied plugins/proxy-loader.php to /var/www/html/wp-content/mu-plugins/proxy-loader.php" && cp -r "$HOME/gitops/plugins/proxy-loader.php" "/var/www/html/wp-content/mu-plugins/proxy-loader.php" && 
  chown -R 0:0 "/var/www/html/wp-content/mu-plugins/proxy-loader.php" && 
  chmod -R 555 "/var/www/html/wp-content/mu-plugins/proxy-loader.php" && 

  echo "[*] > Plugin ops complete.";
else 
  echo "[!] > enabled.txt not found."; 
fi;


echo "6. Complete."