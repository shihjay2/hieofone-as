#!/bin/sh
# ssl installation script

set -e

WEB_CONF=/etc/apache2/conf-enabled
UBUNTU_VER=$(lsb_release -rs)
APACHE_VER=$(apache2 -v | awk -F"[..]" 'NR<2{print $2}')
WEB=/opt
HIE=$WEB/hieofone-as
SSLCRON=/etc/cron.d/certbot
NOSH_DIR=/noshdocuments
NEWNOSH=$NOSH_DIR/nosh2
WEB_GROUP=www-data
WEB_USER=www-data

generate_post_data()
{
  cat <<EOF
{
  "email": "$EMAIL",
  "url": "$DOMAIN",
  "password": "$PASS",
  "username": "$USERNAME"
}
EOF
}

# Check if running as root user
if [[ $EUID -ne 0 ]]; then
	echo "This script must be run as root.  Aborting." 1>&2
	exit 1
fi

read -e -p "Enter email address: " -i "" EMAIL
read -e -p "Enter the domain name (example.com): " -i "" DOMAIN

USERNAME=$(echo "$EMAIL" | cut -d@ -f1)
if [ -f  $HIE/.email ]; then
	rm -rf  $HIE/.email
fi
touch $HIE/.email
chown $WEB_GROUP.$WEB_USER $HIE/.email
chmod 755 $HIE/.email
echo $EMAIL >> $HIE/.email

if [[ ! -z $DOMAIN ]]; then
	cd /usr/local/bin
	if [ ! -f /usr/local/bin/certbot-auto ]; then
		wget https://dl.eff.org/certbot-auto
		chmod a+x /usr/local/bin/certbot-auto
	fi
	./certbot-auto --apache certonly -d $DOMAIN
	echo "SSL Certificate set for $DOMAIN"
	if [ -e "$WEB_CONF"/hie.conf ]; then
		rm "$WEB_CONF"/hie.conf
	fi
	touch "$WEB_CONF"/hie.conf
	AS_APACHE_CONF="<VirtualHost _default_:80>
	DocumentRoot $HIE/public/
</VirtualHost>
<IfModule mod_ssl.c>
	<VirtualHost _default_:443>
		DocumentRoot $HIE/public/
		SSLEngine on
		SSLProtocol all -SSLv2 -SSLv3
		ServerName $DOMAIN
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
	AllowOverride None"
	if [ "$APACHE_VER" = "4" ]; then
		AS_APACHE_CONF="$AS_APACHE_CONF
		Require all granted"
	else
		AS_APACHE_CONF="$AS_APACHE_CONF
		Order allow,deny
		allow from all"
	fi
	AS_APACHE_CONF="$AS_APACHE_CONF
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
	echo "$AS_APACHE_CONF" >> "$WEB_CONF"/hie.conf
	echo "Restarting Apache service."
	/etc/init.d/apache2 restart
	echo "Restarting Apache service."
	if [ -f $SSLCRON ]; then
		rm -rf $SSLCRON
	fi
	touch $SSLCRON
	echo "30 0    * * 1   root    /usr/local/bin/certbot-auto renew >>  /var/log/le-renew.log" >> $SSLCRON
	chown root.root $SSLCRON
	chmod 644 $SSLCRON
	echo "Created LetsEncrypt cron scripts."
	PASS=`tr -dc A-Za-z0-9_ < /dev/urandom | head -c8`
	adduser --disabled-login --gecos "" $USERNAME
	adduser $USERNAME sudo
	echo "$USERNAME:$PASS" | chpasswd
	chage -d 0 $USERNAME
	curl -d "$(generate_post_data)" -H "Content-Type: application/json" -X POST https://dir.hieofone.org/container_create/complete
	echo ""
fi
exit 0
