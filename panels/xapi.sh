#!/bin/bash

# Update package lists
sudo apt update

# Install software-properties-common if not present
sudo apt install -y software-properties-common

# Add the ondrej/php PPA
sudo add-apt-repository -y ppa:ondrej/php

# Update package lists again after adding the PPA
sudo apt update

# Install nginx
sudo apt install -y nginx

# Allow Nginx Full profile through the firewall
sudo ufw allow 'Nginx Full'

# Install PHP 8.1 and PHP-FPM
sudo apt install -y php8.1-fpm

sudo apt install php8.1-curl -y

# Create directories and set permissions
mkdir -p "/var/www/api"
chmod 7777 "/var/www/html/cookie"

# Install PHP SQLite3 extension
sudo apt-get install -y php8.1-sqlite3

# Replace the nginx default site configuration
sudo bash -c 'cat > /etc/nginx/sites-available/default <<EOF
server {
    listen 80;
    server_name _;
    root /var/www;
    index index.html index.htm index.php;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF'

# Define the GitHub repository URL
REPO_URL="https://raw.githubusercontent.com/hossein75rn/xapi/main/panels"

# List of files to download
FILES=("api.php" "countClients.php" "findClient.php" "insertClient.php")

# Download and move files to /var/www/api
for file in "${FILES[@]}"; do
    curl -sSL "${REPO_URL}/${file}" -o "/var/www/api/${file}"
    if [ $? -eq 0 ]; then
        echo "Downloaded ${file} to /var/www/api/"
    else
        echo "Error downloading ${file} from ${REPO_URL}"
    fi
done

# Set appropriate permissions for the PHP files
sudo chmod 644 /var/www/api/*.php

# Reload nginx to apply the new configuration
sudo systemctl restart php8.1-fpm
sudo systemctl reload nginx
