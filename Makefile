# Zairakai Laravel Dev Tools - Project Makefile
# This file includes shared targets from vendor/zairakai/laravel-dev-tools

LARAVEL_DIRECTORY_TOOLS_PROJECT_ROOT := $(shell pwd)
LARAVEL_DIRECTORY_TOOLS_PROJECT_NAME := French-zip-code

.DEFAULT_GOAL := help

# Include shared tooling from Zairakai Laravel Dev Tools
include vendor/zairakai/laravel-dev-tools/tools/make/core.mk

# Override Docker container name if needed (default: app)
# ZAIRAKAI_DOCKER_APP := my-app-container

# Override Pint command if needed (e.g., for custom Docker setup)
# CMD_PINT := docker exec my-app vendor/bin/pint

# Override PHPStan command if needed
# CMD_PHPSTAN := docker exec my-app vendor/bin/phpstan

# Add your custom project-specific targets below
# Example:
# .PHONY: deploy
# deploy: ## Deploy the application
# 	@echo "Deploying application…"

# Docker runtime of the project (the quality tools above run on the host)
COMPOSE := docker compose -p frenchzipcode
ARTISAN := $(COMPOSE) exec -T php php artisan

## —— 🐳 Docker ——

.PHONY: start
start: ## Start the containers (php, mysql, redis, horizon) and prepare the application
	$(COMPOSE) up -d mysql redis php
	$(COMPOSE) exec -T php composer install --no-interaction --prefer-dist --optimize-autoloader
	@test -f .env || cp .env.example .env
	$(COMPOSE) exec -T php sh -c 'grep -q "^APP_KEY=base64" .env || php artisan key:generate'
	$(ARTISAN) migrate --force
	$(COMPOSE) --profile queue up -d horizon

.PHONY: stop
stop: ## Stop the project and remove its containers, network and volumes
	$(COMPOSE) down -v --remove-orphans

.PHONY: ssh
ssh: ## Open a shell in the php container
	$(COMPOSE) exec php bash

## —— 🗄️ Database ——

.PHONY: migrate
migrate: ## Run the database migrations
	$(ARTISAN) migrate --force

.PHONY: db-reset
db-reset: ## Drop all the tables and migrate again
	$(ARTISAN) migrate:fresh --force

## —— 🗺️ Dataset ——

.PHONY: update
update: ## Queue an update of the dataset (official files, then the point of each zip code)
	$(ARTISAN) zipcode:update

.PHONY: update-sync
update-sync: ## Run an update in this terminal, without the queue
	$(COMPOSE) exec php php artisan zipcode:update --sync

.PHONY: status
status: ## Show the state of the dataset and of the last update
	$(ARTISAN) zipcode:status

.PHONY: horizon-status
horizon-status: ## Show whether the Horizon workers are running
	$(ARTISAN) horizon:status

.PHONY: horizon-logs
horizon-logs: ## Follow the logs of the Horizon workers
	$(COMPOSE) logs -f horizon
