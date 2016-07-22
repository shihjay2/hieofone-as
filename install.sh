#!/bin/sh
# install script for nosh-cs

set -e

# Constants and paths
LOGDIR=/var/log/hieofone-as
LOG=$LOGDIR/installation_log
WEB=/opt
HIE=$WEB/hieofone-as
ENV=$HIE/.env

log_only () {
	echo "$1"
	echo "`date`: $1" >> $LOG
}

unable_exit () {
	echo "$1"
	echo "`date`: $1" >> $LOG
	echo "EXITING.........."
	echo "`date`: EXITING.........." >> $LOG
	exit 1
}

get_settings () {
	echo `grep -i "^[[:space:]]*$1[[:space:]=]" $2 | cut -d \= -f 2 | cut -d \; -f 1 | sed "s/[ 	'\"]//gi"`
}

insert_settings () {
	sed -i 's%^[ 	]*'"$1"'[ 	=].*$%'"$1"' = '"$2"'%' "$3"
}

# Check if running as root user
if [[ $EUID -ne 0 ]]; then
	echo "This script must be run as root.  Aborting." 1>&2
	exit 1
fi

# Create log file if it doesn't exist
if [ ! -d $LOGDIR ]; then
	mkdir -p $LOGDIR
fi

read -e -p "Enter the name of the MySQL database that HIE of One Authorization Server will use: " -i "oidc" MYSQL_DATABASE
read -e -p "Enter your MySQL username: " -i "" MYSQL_USERNAME
read -e -p "Enter your MySQL password: " -i "" MYSQL_PASSWORD

# Check os and distro
if [[ "$OSTYPE" == "linux-gnu" ]]; then
	if [ -f /etc/debian_version ]; then
		# Ubuntu or Debian
		WEB_GROUP=www-data
		WEB_GROUP=www-data
		if [ -d /etc/apache2/conf-enabled ]; then
			WEB_CONF=/etc/apache2/conf-enabled
		else
			WEB_CONF=/etc/apache2/conf.d
		fi
		APACHE="/etc/init.d/apache2 restart"
	elif [ -f /etc/redhat-release ]; then
		# CentOS or RHEL
		WEB_GROUP=apache
		WEB_GROUP=apache
		WEB_CONF=/etc/httpd/conf.d
		APACHE="/etc/init.d/httpd restart"
	elif [ -f /etc/arch-release ]; then
		# ARCH
		WEB_GROUP=http
		WEB_GROUP=http
		WEB_CONF=/etc/httpd/conf/extra
		APACHE="systemctl restart httpd.service"
	elif [ -f /etc/gentoo-release ]; then
		# Gentoo
		WEB_GROUP=apache
		WEB_GROUP=apache
		WEB_CONF=/etc/apache2/modules.d
		APACHE=/etc/init.d/apache2
	elif [ -f /etc/fedora-release ]; then
		# Fedora
		WEB_GROUP=apache
		WEB_GROUP=apache
		WEB_CONF=/etc/httpd/conf.d
		APACHE="/etc/init.d/httpd restart"
	fi
elif [[ "$OSTYPE" == "darwin"* ]]; then
	# Mac
	WEB_GROUP=_www
	WEB_GROUP=_www
	WEB_CONF=/etc/httpd/conf.d
	APACHE="/usr/sbin/apachectl restart"
elif [[ "$OSTYPE" == "cygwin" ]]; then
	echo "This operating system is not supported by this install script at this time.  Aborting." 1>&2
	exit 1
elif [[ "$OSTYPE" == "win32" ]]; then
	echo "This operating system is not supported by this install script at this time.  Aborting." 1>&2
	exit 1
elif [[ "$OSTYPE" == "freebsd"* ]]; then
	WEB_GROUP=www
	WEB_GROUP=www
	WEB_CONF=/etc/httpd/conf.d
	if [ -e /usr/local/etc/rc.d/apache22.sh ]; then
		APACHE="/usr/local/etc/rc.d/apache22.sh restart"
	else
		APACHE="/usr/local/etc/rc.d/apache24.sh restart"
	fi
else
	echo "This operating system is not supported by this install script at this time.  Aborting." 1>&2
	exit 1
fi

# Check prerequisites
type apache2 >/dev/null 2>&1 || { echo >&2 "Apache Web Server is required, but it's not installed.  Aborting."; exit 1; }
type mysql >/dev/null 2>&1 || { echo >&2 "MySQL is required, but it's not installed.  Aborting."; exit 1; }
type php >/dev/null 2>&1 || { echo >&2 "PHP is required, but it's not installed.  Aborting."; exit 1; }
type curl >/dev/null 2>&1 || { echo >&2 "cURL is required, but it's not installed.  Aborting."; exit 1; }
log_only "All prerequisites for installation are met."

# Check apache version
APACHE_VER=$(apache2 -v | awk -F"[..]" 'NR<2{print $2}')

# Install
if [ -f /etc/debian_version ]; then
	if [ -d /etc/php5/mods-available ]; then
		if [ ! -f /etc/php5/mods-available/mcrypt.ini ]; then
			if ! [ -L /etc/php5/mods-available/mcrypt.ini ]; then
				ln -s /etc/php5/conf.d/mcrypt.ini /etc/php5/mods-available
				log_only "Enabled mycrpt module for PHP."
			fi
		fi
	fi
	if [ -f /usr/sbin/php5enmod ]; then
		php5enmod mcrypt
		php5enmod imap
		log_only "Enabled mycrpt module for PHP."
	fi
else
	log_only "Ensure you have enabled the mcrypt module for PHP.  Check you distribution help pages to do this."
fi
if [ ! -f /usr/local/bin/composer ]; then
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar /usr/local/bin/composer
fi
log_only "Installed composer.phar."
cd $WEB
composer create-project hieofone-as/hieofone-as --prefer-dist --stability dev
cd $HIE
# Edit .env file
echo "URI=localhost

TWITTER_KEY=yourkeyfortheservice
TWITTER_SECRET=yoursecretfortheservice
TWITTER_REDIRECT_URI=https://example.com/login

GOOGLE_KEY=yourkeyfortheservice
GOOGLE_SECRET=yoursecretfortheservice
GOOGLE_REDIRECT_URI=https://example.com/login" >> $ENV
sed -i '/^DB_DATABASE=/s/=.*/='"$MYSQL_DATABASE"'/' .env
sed -i '/^DB_USERNAME=/s/=.*/='"$MYSQL_USERNAME"'/' .env
sed -i '/^DB_PASSWORD=/s/=.*/='"$MYSQL_PASSWORD"'/' .env
chown -R $WEB_GROUP.$WEB_USER $HIE
chmod -R 755 $HIE
chmod -R 777 $HIE/storage
chmod -R 777 $HIE/public
log_only "Installed HIE of One Authorization Server core files."
echo "create database $MYSQL_DATABASE" | mysql -u $MYSQL_USERNAME -p$MYSQL_PASSWORD
php artisan migrate:install
php artisan migrate

# Set up SSL and configuration file for Apache server
if [ -f /etc/debian_version ]; then
	if [ ! -f /etc/apache2/sites-available/default-ssl.conf ]; then
		if ! [ -L /etc/apache2/sites-enabled/default-ssl ]; then
			log_only "Setting up Apache to use SSL using the default-ssl virtual host for Ubuntu/Debian."
			ln -s /etc/apache2/sites-available/default-ssl /etc/apache2/sites-enabled/default-ssl
		fi
	else
		if ! [ -L /etc/apache2/sites-enabled/default-ssl.conf ]; then
			log_only "Setting up Apache to use SSL using the default-ssl virtual host for Ubuntu/Debian."
			ln -s /etc/apache2/sites-available/default-ssl.conf /etc/apache2/sites-enabled/default-ssl.conf
		fi
	fi
	a2enmod ssl
	a2enmod rewrite
else
	log_only "You will need to enable/create a virtual host and the SSL module for Apache before HIE of One Authorization Server will work securely."
fi
if [ -e "$WEB_CONF"/hie.conf ]; then
	rm "$WEB_CONF"/hie.conf
fi
touch "$WEB_CONF"/hie.conf
APACHE_CONF="<VirtualHost _default_:80>
	DocumentRoot $HIE/public/
	ErrorLog ${APACHE_LOG_DIR}/error.log
	CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
<IfModule mod_ssl.c>
	<VirtualHost _default_:443>
		DocumentRoot $HIE/public/
		ErrorLog ${APACHE_LOG_DIR}/error.log
		CustomLog ${APACHE_LOG_DIR}/access.log combined
		SSLEngine on
		SSLProtocol all -SSLv2 -SSLv3
		SSLCertificateFile /etc/ssl/certs/ssl-cert-snakeoil.pem
        SSLCertificateKeyFile /etc/ssl/private/ssl-cert-snakeoil.key
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
	RewriteRule ^(.*)/$ /$1 [L,R=301]
	RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
	# Handle Front Controller...
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^ index.php [L]
	<IfModule mod_php5.c>
		php_value upload_max_filesize 512M
		php_value post_max_size 512M
		php_flag magic_quotes_gpc off
		php_flag register_long_arrays off
	</IfModule>
</Directory>"
echo "$APACHE_CONF" >> "$WEB_CONF"/hie.conf
log_only "HIE of One Authorization Server Apache configuration file set."
log_only "Restarting Apache service."
$APACHE >> $LOG 2>&1
# Installation completed
log_only "You can now complete your new installation of HIE of One Authorization Server by browsing to:"
log_only "https://localhost/install"
exit 0
