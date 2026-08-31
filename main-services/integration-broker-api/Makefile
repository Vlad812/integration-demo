SERVICE_DIR := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
PROJECT_ROOT := $(abspath $(SERVICE_DIR)/../..)
include $(PROJECT_ROOT)/config.mk

COMPOSE_FILE := $(SERVICE_DIR)/docker-compose.yaml
COMPOSE := docker compose -f $(COMPOSE_FILE) -p $(PROJECT_GROUP_MAIN_SERVICE)

.PHONY: integration-broker-api-build integration-broker-api-init integration-broker-api-update integration-broker-api-up integration-broker-api-down

integration-broker-api-build:
	@echo build integration-broker-api
	$(COMPOSE) build

integration-broker-api-init:
	@echo init integration-broker-api
	$(COMPOSE) run --rm integration-broker-api-php-cli composer install --no-interaction

integration-broker-api-update:
	@echo update integration-broker-api composer
	$(COMPOSE) run --rm integration-broker-api-php-cli composer update --no-interaction

integration-broker-api-up:
	@echo up integration-broker-api
	$(COMPOSE) up -d

integration-broker-api-down:
	@echo down integration-broker-api
	$(COMPOSE) down -v
