#/bin/bash

#Make sure we have the permissoins
#sudo chmod -R g+w .

# Pull master branch
# git reset --hard ??
git pull origin master

#install new dependencies
sudo rm -rf vendor/
composer install --no-dev --optimize-autoloader
php ./bin/console cache:clear --env=prod

#set Environment variables
composer dump-env prod

#Build new .js and .css
yarn install
yarn encore prod

#Migrate DB if necessary
php bin/console doctrine:migrations:migrate -n

sudo chown -R www-data:www-data . 
