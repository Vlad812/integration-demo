MAIN_SERVICES_DIR := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
PROJECT_ROOT := $(abspath $(MAIN_SERVICES_DIR)/..)
include $(PROJECT_ROOT)/config.mk

include $(MAIN_SERVICES_DIR)/integration-broker-api/Makefile

.PHONY: ms-all-init ms-all-build ms-all-up ms-all-down

ms-all-build: integration-broker-api-build

ms-all-init: integration-broker-api-init

ms-all-up: integration-broker-api-up

ms-all-down: integration-broker-api-down
