#!/bin/sh

cd $HOME/gitops && # ensure we are in the correct directory
echo "2. Starting script via git repo..." && 

# chmod 777 . && 
# Set file permissions (primarily a post-restore step)
# find "/var/www/html/*" -type d -exec chmod 755 {} \; && 
# find "/var/www/html/*" -type f -exec chmod 644 {} \; && 

# Plugin ops
echo "[*] > Starting plugin ops..."
if [ -f plugins/enabled.txt ]; then 
  while IFS= read -r f || [ -n "$f" ]; do # IFS= sets whitespace to newline. || [ -n "$f" ] prevents the last line from being ignored.
    if [ -d "/var/www/html/wp-content/plugins/$f" ]; then 
      echo "[-] > Deleted /var/www/html/wp-content/plugins/$f" && rm -rf "/var/www/html/wp-content/plugins/$f"; 
    fi; 
    if [ -d "$HOME/gitops/$f" ]; then 
      echo "[+] > Copied $HOME/gitops/$f to /var/www/html/wp-content/plugins/$f" && cp -r "$HOME/gitops/$f" "/var/www/html/wp-content/plugins/" && 
      chown -R 0:0 "/var/www/html/wp-content/plugins/$f" && 
      chmod -R 555 "/var/www/html/wp-content/plugins/$f" && 
      echo "[*] > Permissions set for /var/www/html/wp-content/plugins/$f";
    fi; 
  done < enabled.txt; 
  echo "[*] > Plugin ops complete.";
else 
  echo "[!] > enabled.txt not found."; 
fi;

echo "3. Complete.