#!/usr/bin/env bash

function update_apt() {
    apt-get update && apt-get upgrade -y
}

function setup_prerequisite() {
    # Adding software-properties-common for add-apt-repository.
    apt-get install -y software-properties-common
    # Adding git for cloning our repo
    apt install -y git
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
}

update_apt
setup_prerequisite
setup_php
download_and_install_bediq
