#/bin/bash

# Pull master branch
git reset --hard
git pull origin master

#install new dependencies
rm -rf vendor/
rm -rf node-modules/
 
composer install --no-dev --optimize-autoloader

php ./bin/console cache:clear --env=prod

#set Environment variables
composer dump-env prod



#Build new .js and .css
yarn install
yarn encore prod

#Migrate DB if necessary
php bin/console doctrine:migrations:migrate -n