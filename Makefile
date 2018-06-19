# 1N73LL1G3NC3 15 7H3 4B1L17Y 70 4D4P7 70 CG4NG3.
# - 57PH3N H4WK1NG

.DEFAULT_GOAL = help
.PHONY: help start stop restart ssh build install composer node front chown-dir migration seed php-cs fix clean dist-clean db-reset queue-listen docker-prune

include .env

PROJECT = frenchzipcode
COMPOSE = docker-compose -p $(PROJECT)
RUN = $(COMPOSE) run --rm fpm
EXEC = docker exec -ti $(PROJECT)_fpm_1
EXPORT = docker exec $(PROJECT)_mysql_1
COMPOSE_HTTP_TIMEOUT = 300

help:	## Show this help
	@grep -h -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ''

start: build install	## Start the project
	$(COMPOSE) up -d fpm
	$(COMPOSE) up -d

stop:	## Stop and clear the project
	docker ps -aq | xargs docker stop
	docker ps -aq | xargs docker rm
	docker volume ls -q | xargs docker volume rm
	docker network prune -f

restart: stop start	## Execute stop and start

ssh:	## Acces to the app
	@$(EXEC) bash

builder:	## Build the database
	$(RUN) php artisan builder:build

export:	## Export the build
	$(RUN) php artisan builder:export
	@$(EXPORT) sh -c 'exec mysqldump -u root --password=root $(DB_DATABASE) regions' > ./Exports/sql/regions.sql
	@$(EXPORT) sh -c 'exec mysqldump -u root --password=root $(DB_DATABASE) departments' > ./Exports/sql/departments.sql
	@$(EXPORT) sh -c 'exec mysqldump -u root --password=root $(DB_DATABASE) cities' > ./Exports/sql/cities.sql

build:	## Pull and build the containers
	$(COMPOSE) pull --ignore-pull-failures
	$(COMPOSE) build --pull --force-rm

install: composer node front chown-dir seed

composer:	## Install or update the composer dependencies
	if [ ! -d vendor ]; then $(RUN) composer install --no-interaction --prefer-dist --optimize-autoloader; else $(RUN) composer dump-autoload; fi

composer-install:	## Update the composer
	$(RUN) rm -f ./composer.lock
	$(RUN) composer install --no-interaction --prefer-dist --optimize-autoloader

node:	## Install or update the node dependencies
	if [ ! -d node_modules ]; then $(RUN) npm install --ignore-engines; fi

front:	## Run the buil for the front
	$(RUN) npm run dev

chown-dir:	## Change the directory owner and access
	$(RUN) chgrp -R www-data /var/www/html
	$(RUN) chmod -R 0777 /var/www/html/docker/apache/logs/ /var/www/html/storage /var/www/html/bootstrap/cache

migration:	## Artisan migrate through docker
	$(RUN) php artisan migrate

seed: migration	## Artisan migrate then seed through docker
	$(RUN) php artisan db:seed

php-cs: ## Run the PHP-CS-Fixer
	$(RUN) php artisan fixer:fix --no-interaction --dry-run --diff --using-cache=no

fix: ## Run the PHP-CS-Fixer to fix the files
	$(RUN) php artisan fixer:fix --using-cache=no

clean:	## Clean the Laravel cahce, view, config, route and delete some directories
	$(RUN) rm -rf public/build/* public/css/* public/js/* storage/debugbar
	$(RUN) php artisan cache:clear
	$(RUN) php artisan view:clear
	$(RUN) php artisan config:clear
	$(RUN) php artisan route:clear

dist-clean: clean	## In addition to "clean" delete the node_modules and vendor directories
	$(RUN) rm -rf node_modules vendor/*

db-reset:	## Rebuild, migrate and seed the database
	$(RUN) php artisan migrate:reset
	$(RUN) php artisan migrate
	$(RUN) php artisan db:seed

queue-listen:	## Show the queue listen by artisan through docker
	$(RUN) php artisan queue:listen

docker-prune:	## Prune the system
	docker system prune -af
