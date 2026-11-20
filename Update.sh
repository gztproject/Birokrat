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

#Move uploaded documents out of the public web directory
for dir in signatures incoming-invoices; do
	if [ -d "public/uploads/$dir" ]; then
		mkdir -p "uploads/$dir"
		find "public/uploads/$dir" -maxdepth 1 -type f -exec mv -n {} "uploads/$dir/" \;
		rmdir "public/uploads/$dir" 2>/dev/null
	fi
done
rmdir public/uploads 2>/dev/null

#Migrate DB if necessary
php bin/console doctrine:migrations:migrate -n