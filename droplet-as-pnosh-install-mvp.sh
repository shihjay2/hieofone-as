#!/bin/sh
# install script for hieofone-as on a droplet - Ubuntu 18.04 server

set -e

# Constants and paths
LOGDIR=/var/log/hieofone-as-pnosh
LOG=$LOGDIR/installation_log
HIECRON=/etc/cron.d/hieofone
MYSQL_DATABASE=nosh
MYSQL_USERNAME=hieofone
AS_MYSQL_DATABASE=oidc
WEB=/opt
HIE=$WEB/hieofone-as
AS_ENV=$HIE/.env
PRIVKEY=$HIE/.privkey.pem
PUBKEY=$HIE/.pubkey.pem
WEB_GROUP=www-data
WEB_USER=www-data
WEB_CONF=/etc/apache2/conf-enabled
APACHE="/etc/init.d/apache2 restart"
NOSH_DIR=/noshdocuments
NEWNOSH=$NOSH_DIR/nosh2
ENV=$NEWNOSH/.env

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

read -e -p "Enter your registered URL: " -i "" URL

# Install PHP and MariaDB
apt-get -y install software-properties-common build-essential binutils-doc git subversion bc apache2 php php-cli php-common php-curl php-gd php-imagick php-imap php-mbstring php-mysql php-pear php-soap php-ssh2 php-xml php-zip libapache2-mod-php libdbi-perl libdbd-mysql-perl libssh2-1-dev imagemagick openssh-server pwgen
export DEBIAN_FRONTEND=noninteractive
# Randomly generated password for MariaDB
MYSQL_PASSWORD=`pwgen -s 40 1`
log_only "Your MariaDB password is $MYSQL_PASSWORD"
debconf-set-selections <<< "mariadb-server-10.1 mysql-server/data-dir select ''"
debconf-set-selections <<< "mariadb-server-10.1 mysql-server/root_password password $MYSQL_PASSWORD"
debconf-set-selections <<< "mariadb-server-10.1 mysql-server/root_password_again password $MYSQL_PASSWORD"
apt-get install -y mariadb-server mariadb-client

# Set default collation and character set
echo "[mysqld]
character_set_server = 'utf8'
collation_server = 'utf8_general_ci'" >> /etc/mysql/my.cnf
# Configure Maria Remote Access - disable for MVP
#sed -i '/^bind-address/s/bind-address.*=.*/bind-address = 0.0.0.0/' /etc/mysql/my.cnf
mysql --user="root" --password="$MYSQL_PASSWORD" -e "GRANT ALL ON *.* TO root@'0.0.0.0' IDENTIFIED BY '$MYSQL_PASSWORD' WITH GRANT OPTION;"
mysql --user="root" --password="$MYSQL_PASSWORD" -e "CREATE USER '$MYSQL_USERNAME'@'0.0.0.0' IDENTIFIED BY '$MYSQL_PASSWORD';"
mysql --user="root" --password="$MYSQL_PASSWORD" -e "GRANT ALL ON *.* TO '$MYSQL_USERNAME'@'0.0.0.0' IDENTIFIED BY '$MYSQL_PASSWORD' WITH GRANT OPTION;"
mysql --user="root" --password="$MYSQL_PASSWORD" -e "GRANT ALL ON *.* TO '$MYSQL_USERNAME'@'%' IDENTIFIED BY '$MYSQL_PASSWORD' WITH GRANT OPTION;"
mysql --user="root" --password="$MYSQL_PASSWORD" -e "FLUSH PRIVILEGES;"
systemctl restart mysql

# Check prerequisites
type apache2 >/dev/null 2>&1 || { echo >&2 "Apache Web Server is required, but it's not installed.  Aborting."; exit 1; }
type mysql >/dev/null 2>&1 || { echo >&2 "MySQL is required, but it's not installed.  Aborting."; exit 1; }
type php >/dev/null 2>&1 || { echo >&2 "PHP is required, but it's not installed.  Aborting."; exit 1; }
type curl >/dev/null 2>&1 || { echo >&2 "cURL is required, but it's not installed.  Aborting."; exit 1; }
log_only "All prerequisites for installation are met."

# Check apache version
APACHE_VER=$(apache2 -v | awk -F"[..]" 'NR<2{print $2}')

# Install
phpenmod imap
if [ ! -f /usr/local/bin/composer ]; then
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar /usr/local/bin/composer
fi
log_only "Installed composer.phar."
cd $WEB
composer create-project hieofone-as/hieofone-as --prefer-dist --stability dev
cd $HIE
# Edit .env file
echo "TRUSTED_PROXIES=
URI=localhost

TWITTER_KEY=yourkeyfortheservice
TWITTER_SECRET=yoursecretfortheservice
TWITTER_REDIRECT_URI=https://example.com/login

GOOGLE_KEY=yourkeyfortheservice
GOOGLE_SECRET=yoursecretfortheservice
GOOGLE_REDIRECT_URI=https://example.com/login
INSTALL_TYPE=UMA
" >> $AS_ENV
sed -i '/^DB_DATABASE=/s/=.*/='"$AS_MYSQL_DATABASE"'/' .env
sed -i '/^DB_USERNAME=/s/=.*/='"$MYSQL_USERNAME"'/' .env
sed -i '/^DB_PASSWORD=/s/=.*/='"$MYSQL_PASSWORD"'/' .env
openssl genrsa -out $PRIVKEY 2048
openssl rsa -in $PRIVKEY -pubout -out $PUBKEY
chown -R $WEB_GROUP.$WEB_USER $HIE
chmod -R 755 $HIE
chmod -R 777 $HIE/storage
chmod -R 777 $HIE/public
log_only "Installed HIE of One Authorization Server core files."
echo "create database $AS_MYSQL_DATABASE" | sudo mysql -u $MYSQL_USERNAME -p$MYSQL_PASSWORD
php artisan migrate:install
php artisan migrate
a2enmod rewrite
a2enmod ssl

# Create cron scripts
if [ -f $HIECRON ]; then
	rm -rf $HIECRON
fi
touch $HIECRON
echo "*/10 *  * * *   root    $NEWNOSH/noshfax" >> $HIECRON
echo "*/1 *   * * *   root    $NEWNOSH/noshreminder" >> $HIECRON
echo "0 0     * * *   root    $NEWNOSH/noshbackup" >> $HIECRON
chown root.root $HIECRON
chmod 644 $HIECRON
log_only "Created cron scripts."

phpenmod imap
if [ ! -f /usr/local/bin/composer ]; then
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar /usr/local/bin/composer
fi
log_only "Installed composer.phar."
if [ -d $NOSH_DIR ]; then
	log_only "The NOSH ChartingSystem documents directory already exists."
else
	mkdir -p $NOSH_DIR
	log_only "The NOSH ChartingSystem documents directory has been created."
fi
chown -R $WEB_GROUP.$WEB_USER "$NOSH_DIR"
chmod -R 755 $NOSH_DIR
if ! [ -d "$NOSH_DIR"/scans ]; then
	mkdir "$NOSH_DIR"/scans
	chown -R $WEB_GROUP.$WEB_USER "$NOSH_DIR"/scans
	chmod -R 777 "$NOSH_DIR"/scans
fi
if ! [ -d "$NOSH_DIR"/received ]; then
	mkdir "$NOSH_DIR"/received
	chown -R $WEB_GROUP.$WEB_USER "$NOSH_DIR"/received
fi
if ! [ -d "$NOSH_DIR"/sentfax ]; then
	mkdir "$NOSH_DIR"/sentfax
	chown -R $WEB_GROUP.$WEB_USER "$NOSH_DIR"/sentfax
fi
log_only "The NOSH ChartingSystem scan and fax directories are secured."
log_only "The NOSH ChartingSystem documents directory is secured."
log_only "This installation will create pNOSH (patient NOSH)."
# Build
cd $NOSH_DIR
composer create-project nosh2/nosh2 --prefer-dist --stability dev
cd $NEWNOSH
# pNOSH designation
if ! [ -f "$NEWNOSH"/.patientcentric ]; then
	touch "$NEWNOSH"/.patientcentric
fi
# Edit .env file
sed -i '/^DB_DATABASE=/s/=.*/='"$MYSQL_DATABASE"'/' $ENV
sed -i '/^DB_USERNAME=/s/=.*/='"$MYSQL_USERNAME"'/' $ENV
sed -i '/^DB_PASSWORD=/s/=.*/='"$MYSQL_PASSWORD"'/' $ENV
echo "TRUSTED_PROXIES=
URI=localhost

TWITTER_KEY=yourkeyfortheservice
TWITTER_SECRET=yoursecretfortheservice
TWITTER_REDIRECT_URI=https://example.com/login

GOOGLE_KEY=yourkeyfortheservice
GOOGLE_SECRET=yoursecretfortheservice
GOOGLE_REDIRECT_URI=https://example.com/login
" >> $ENV

chown -R $WEB_GROUP.$WEB_USER $NEWNOSH
chmod -R 755 $NEWNOSH
chmod -R 777 $NEWNOSH/storage
chmod -R 777 $NEWNOSH/public
chmod 777 $NEWNOSH/noshfax
chmod 777 $NEWNOSH/noshreminder
chmod 777 $NEWNOSH/noshbackup
log_only "Installed NOSH ChartingSystem core files."
echo "create database $MYSQL_DATABASE" | sudo mysql -u $MYSQL_USERNAME -p$MYSQL_PASSWORD
php artisan migrate:install
php artisan migrate
log_only "Installed NOSH ChartingSystem database schema."

# Installation completed
echo 'alias install-trustee="sudo bash /opt/hieofone-as/ssl-install-complete.sh"' >> /root/.bashrc
log_only "Trustee MVP Base installation complete.  Run install-trustee once a domain name is set and to set a temporary password"
exit 0
