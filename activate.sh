#!/usr/bin/env bash

function activate() {
    echo "Activating bedIQ cli ... $1"
    cp -rp ./.env.example ./.env

    # PUT BEDIQ_KEY TO ENV
}

activate $1

