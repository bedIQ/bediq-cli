#!/usr/bin/env bash
export DEBIAN_FRONTEND=noninteractive

function update_apt() {
    echo "Updating apt..."
    apt-get update > /dev/null 2>&1
    apt-get autoremove -y > /dev/null 2>&1
}

function setup_prerequisite() {
    echo "Installing pre-requisites..."
    # Adding software-properties-common for add-apt-repository.
    apt-get install -y software-properties-common python-certbot-nginx

    add-apt-repository -y ppa:certbot/certbot

    apt-get install -y git

    apt-get install -y zip unzip tzdata

    # Set timezone
    ln -fs /usr/share/zoneinfo/UTC /etc/localtime
    dpkg-reconfigure -f noninteractive tzdata

    # restart cron service
    service cron restart
}

function setup_composer() {
    if ! command -v composer >/dev/null 2>&1; then
        echo "Installing Composer..."
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        php composer-setup.php --quiet
        rm composer-setup.php
        mv composer.phar /usr/local/bin/composer
    fi
}

function setup_php() {
    if ! command -v php >/dev/null 2>&1; then
        echo "Installing PHP CLI..."
        # Adding ondrej/php repository for installing php, this works for all ubuntu flavours.
        add-apt-repository -y ppa:ondrej/php
        apt-get update > /dev/null 2>&1
        # Installing php-cli, which is the minimum requirement to run EasyEngine
        apt-get -y install php7.3-cli php7.3-curl php7.3-zip php7.3-xml
    fi
}

function install_bediq_cli() {
    if ! command -v bediq >/dev/null 2>&1; then
        echo "Downloading bedIQ CLI"
        git clone https://github.com/bedIQ/bediq-cli.git /opt/bediq-cli
        ln -s /opt/bediq-cli/bediq /usr/local/bin/bediq
        cd /opt/bediq-cli
        composer install --no-interaction --prefer-dist --optimize-autoloader > /dev/null 2>&1
        echo "bedIQ CLI installed"
    fi
}

function provision_vm() {
    echo "Starting bediq provision:vm ..."
    bediq provision:vm
}

function server_cache() {
    echo "Fetching bediq server cache files ..."
    bediq tools:update
}

update_apt
setup_prerequisite
setup_php
setup_composer
install_bediq_cli
provision_vm
server_cache

source /opt/bediq-cli/activate.sh
