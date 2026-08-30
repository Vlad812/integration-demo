COMMON_SERVICES_DIR := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
PROJECT_ROOT := $(abspath $(COMMON_SERVICES_DIR)/..)
include $(PROJECT_ROOT)/config.mk

.PHONY: cs-traefik-up cs-traefik-down

cs-traefik-up:
	@echo up traefik
	docker compose -f $(COMMON_SERVICES_DIR)/traefik/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) up -d

cs-traefik-down:
	@echo down traefik
	docker compose -f $(COMMON_SERVICES_DIR)/traefik/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) down -v

.PHONY: cs-buggregator-up cs-buggregator-down

cs-buggregator-up:
	@echo up buggregator
	docker compose -f $(COMMON_SERVICES_DIR)/buggregator/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) up -d

cs-buggregator-down:
	@echo down buggregator
	docker compose -f $(COMMON_SERVICES_DIR)/buggregator/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) down -v

.PHONY: cs-postgresql-up cs-postgresql-down cs-pg-integration-init

cs-pg-integration-init:
	@echo init cs-pg-integration
	docker compose -f $(COMMON_SERVICES_DIR)/postgresql/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) up -d cs-pg-integration
	@until docker compose -f $(COMMON_SERVICES_DIR)/postgresql/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) exec -T cs-pg-integration pg_isready -U my_postgres -d integration_broker_db 2>/dev/null; do \
		echo "Waiting..."; \
		sleep 1; \
	done
	@echo "cs-pg-integration initialized, stopping container..."
	docker compose -f $(COMMON_SERVICES_DIR)/postgresql/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) stop cs-pg-integration

cs-postgresql-up:
	@echo up postgresql
	docker compose -f $(COMMON_SERVICES_DIR)/postgresql/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) up -d

cs-postgresql-down:
	@echo down postgresql
	docker compose -f $(COMMON_SERVICES_DIR)/postgresql/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) down -v

.PHONY: cs-rabbit-up cs-rabbit-down

cs-rabbit-up:
	@echo up rabbit
	docker compose -f $(COMMON_SERVICES_DIR)/rabbit/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) up -d

cs-rabbit-down:
	@echo down rabbit
	docker compose -f $(COMMON_SERVICES_DIR)/rabbit/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) down -v

.PHONY: cs-redis-up cs-redis-down

cs-redis-up:
	@echo up redis
	docker compose -f $(COMMON_SERVICES_DIR)/redis/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) up -d

cs-redis-down:
	@echo down redis
	docker compose -f $(COMMON_SERVICES_DIR)/redis/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) down -v

.PHONY: cs-wiremock-up cs-wiremock-down

cs-wiremock-up:
	@echo up wiremock
	docker compose -f $(COMMON_SERVICES_DIR)/wiremock/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) up -d

cs-wiremock-down:
	@echo down wiremock
	docker compose -f $(COMMON_SERVICES_DIR)/wiremock/compose.yaml -p $(PROJECT_GROUP_COMMON_SERVICE) down -v

.PHONY: cs-all-up cs-all-down cs-all-init

cs-all-init: cs-pg-integration-init

cs-all-up: cs-traefik-up cs-buggregator-up cs-postgresql-up cs-rabbit-up cs-redis-up cs-wiremock-up

cs-all-down: cs-wiremock-down cs-redis-down cs-rabbit-down cs-buggregator-down cs-traefik-down cs-postgresql-down
