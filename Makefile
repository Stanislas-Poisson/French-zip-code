# 1N73LL1G3NC3 15 7H3 4B1L17Y 70 4D4P7 70 CG4NG3.
# - 57PH3N H4WK1NG

.DEFAULT_GOAL = help
.PHONY: help start stop restart ssh builder export build install composer migration pint clean dist-clean db-reset docker-prune

include .env

PROJECT = frenchzipcode
COMPOSE = docker compose -p $(PROJECT)
RUN = $(COMPOSE) run --rm php
EXEC = docker exec -ti $(PROJECT)-php-1
EXPORT = docker exec $(PROJECT)-mysql-1
COMPOSE_HTTP_TIMEOUT = 300

help:	## Show this help
	@grep -h -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ''

start: build install	## Start the project
	$(COMPOSE) up -d

stop:	## Stop and clear the project (containers, network and database volume)
	$(COMPOSE) down -v --remove-orphans

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
	$(COMPOSE) build --pull

install: composer migration

composer:	## Install or update the composer dependencies
	if [ ! -d vendor ]; then $(RUN) composer install --no-interaction --prefer-dist --optimize-autoloader; else $(RUN) composer dump-autoload; fi

migration:	## Artisan migrate through docker
	$(RUN) php artisan migrate

pint:	## Run Laravel Pint to fix the code style
	$(RUN) vendor/bin/pint

clean:	## Clean the Laravel cache and config
	$(RUN) php artisan cache:clear
	$(RUN) php artisan config:clear

dist-clean: clean	## In addition to "clean" delete the vendor directory
	$(RUN) rm -rf vendor/*

db-reset:	## Drop all the tables and migrate again
	$(RUN) php artisan migrate:fresh

docker-prune:	## Prune the system
	docker system prune -af
