#!/bin/bash

set -e

BACKEND_DIR=$(pwd)/backend
MOBILE_DIR=$(pwd)/mobile


echo -e "\n\n-> PULLING CHANGES \n\n"

git add .
git stash
git pull
git stash pop

echo -e "\n\n-> DEPLOYING BACKEND \n\n"

cd $BACKEND_DIR
composer install
php artisan migrate
php artisan optimize

echo -e "\n\n-> DEPLOYING MOBILE \n\n"

cd $MOBILE_DIR
npm install
npm run build-only

echo -e "\n\n-> DEPLOYMENT COMPLETED \n\n"
