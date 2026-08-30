PROJECT_ROOT := $(patsubst %/,%,$(dir $(abspath $(lastword $(MAKEFILE_LIST)))))
include $(PROJECT_ROOT)/config.mk
include common-services/common-services.mk
include main-services/main-services.mk

.PHONY: create-network remove-network app-setup app-up app-down app-clear gen-jwt gen-jwt-token

app-setup: create-network ms-all-build ms-all-init cs-all-init gen-jwt
app-up: create-network cs-all-up ms-all-up
app-down: ms-all-down cs-all-down
app-clear: ms-all-down cs-all-down remove-network

create-network:
	docker network inspect $(NETWORK_NAME) >/dev/null 2>&1 || docker network create $(NETWORK_NAME)

remove-network:
	docker network rm $(NETWORK_NAME) 2>/dev/null || true

gen-jwt:
	@sed -i 's/\r$$//' $(PROJECT_ROOT)/util/gen_jwt
	@chmod +x $(PROJECT_ROOT)/util/gen_jwt
	@$(PROJECT_ROOT)/util/gen_jwt

CLIENT_ID ?= usr_987654321

gen-jwt-token:
	@sed -i 's/\r$$//' $(PROJECT_ROOT)/util/gen_jwt_token
	@chmod +x $(PROJECT_ROOT)/util/gen_jwt_token
	@$(PROJECT_ROOT)/util/gen_jwt_token $(CLIENT_ID)
