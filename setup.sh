#!/usr/bin/env bash
DEBIAN_FRONTEND=noninteractive

function update_apt() {
    apt-get update && apt-get upgrade -y
}

function setup_prerequisite() {
    # Adding software-properties-common for add-apt-repository.
    apt-get install -y software-properties-common
    # Adding git for cloning our repo
    apt install -y git

    # Set timezone
    timedatectl set-timezone UTC

    # restart cron service
    service cron restart
}

function setup_composer() {
    EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]
    then
        >&2 echo 'ERROR: Invalid installer signature'
        rm composer-setup.php
        exit 1
    fi

    php composer-setup.php --quiet
    RESULT=$?
    rm composer-setup.php
    echo $RESULT
}

function setup_php() {
    echo "Installing PHP"
    if ! command -v php >/dev/null 2>&1; then
        echo "Installing PHP cli"
        # Adding ondrej/php repository for installing php, this works for all ubuntu flavours.
        add-apt-repository -y ppa:ondrej/php
        apt-get update
        # Installing php-cli, which is the minimum requirement to run EasyEngine
        apt-get -y install php7.3-cli php7.3-curl php7.3-zip
    fi
}

function download_and_install_bediq() {
    echo "Downloading bedIQ CLI"
    git clone https://github.com/bedIQ/bediq-cli.git /opt/bediq-cli
    ln -s /opt/bediq-cli/bediq /usr/local/bin/bediq
    cd /opt/bediq-cli
    composer install --no-interaction --prefer-dist --optimize-autoloader
}

update_apt
setup_prerequisite
setup_php
setup_composer
download_and_install_bediq
