#!/bin/sh
# ssl installation script

set -e

WEB_CONF=/etc/apache2/conf-enabled
UBUNTU_VER=$(lsb_release -rs)
APACHE_VER=$(apache2 -v | awk -F"[..]" 'NR<2{print $2}')

# Check if running as root user
if [[ $EUID -ne 0 ]]; then
	echo "This script must be run as root.  Aborting." 1>&2
	exit 1
fi

read -e -p "Enter your domain name (example.com): " -i "" DOMAIN

if [[ ! -z $DOMAIN ]]; then
	if [ ! -f /usr/local/bin/certbot-auto ]; then
		cd /usr/local/bin
		wget https://dl.eff.org/certbot-auto
		chmod a+x /usr/local/bin/certbot-auto
		./certbot-auto --apache certonly -d $DOMAIN
	fi

	if [ -e "$WEB_CONF"/hie.conf ]; then
		rm "$WEB_CONF"/hie.conf
	fi
	touch "$WEB_CONF"/hie.conf
	APACHE_CONF="<VirtualHost _default_:80>
	DocumentRoot $HIE/public/
</VirtualHost>
<IfModule mod_ssl.c>
	<VirtualHost _default_:443>
		DocumentRoot $HIE/public/
		SSLEngine on
		SSLProtocol all -SSLv2 -SSLv3
		ServerName as1.hieofone.org
		SSLCertificateFile /etc/letsencrypt/live/$DOMAIN/fullchain.pem
		SSLCertificateKeyFile /etc/letsencrypt/live/$DOMAIN/privkey.pem
		Include /etc/letsencrypt/options-ssl-apache.conf
		<FilesMatch \"\.(cgi|shtml|phtml|php)$\">
			SSLOptions +StdEnvVars
		</FilesMatch>
		<Directory /usr/lib/cgi-bin>
			SSLOptions +StdEnvVars
        </Directory>
		BrowserMatch \"MSIE [2-6]\" \
		nokeepalive ssl-unclean-shutdown \
		downgrade-1.0 force-response-1.0
		BrowserMatch \"MSIE [17-9]\" ssl-unclean-shutdown
	</VirtualHost>
</IfModule>
<Directory $HIE/public>
	Options Indexes FollowSymLinks MultiViews
	AllowOverride All"
	if [ "$APACHE_VER" = "4" ]; then
		APACHE_CONF="$APACHE_CONF
		Require all granted"
	else
		APACHE_CONF="$APACHE_CONF
		Order allow,deny
		allow from all"
	fi
	APACHE_CONF="$APACHE_CONF
	RewriteEngine On
	# Redirect Trailing Slashes...
	RewriteRule ^(.*)/$ /\$1 [L,R=301]
	RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
	# Handle Front Controller...
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^ index.php [L]
	# Force SSL
	RewriteCond %{HTTPS} !=on
	RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
	<IfModule mod_php5.c>
		php_value upload_max_filesize 512M
		php_value post_max_size 512M
		php_flag magic_quotes_gpc off
		php_flag register_long_arrays off
	</IfModule>
</Directory>"
	echo "$APACHE_CONF" >> "$WEB_CONF"/hie.conf
	echo "SSL Certificate for $DOMAIN set"
	/etc/init.d/apache2 restart
	echo "Restarting Apache service."
fi
exit 0
