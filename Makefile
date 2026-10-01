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
start: ## Start the containers (php, mysql, redis) and install the dependencies
	$(COMPOSE) up -d
	$(COMPOSE) exec -T php composer install --no-interaction --prefer-dist --optimize-autoloader

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
