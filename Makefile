CLI_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
$(eval $(sort $(subst :,\:,$(CLI_ARGS))):;@:)

PRIMARY_GOAL := $(firstword $(MAKECMDGOALS))
ifeq ($(PRIMARY_GOAL),)
    PRIMARY_GOAL := help
endif

include docker/.env
ifneq (,$(wildcard .env))
    include .env
endif
QUEUE_DRIVER ?= amqp
EXTRACTOR_ADAPTER ?= kreuzberg
WORKERS ?= 2
LLM_ADAPTER ?= llamacpp
export QUEUE_DRIVER
export EXTRACTOR_ADAPTER
export DATABASE_DSN
export DOCUMENT_STORAGE_DRIVER
export DOCUMENT_LOCAL_STORAGE_ROOT
export S3_ENDPOINT
export S3_REGION
export S3_BUCKET
export S3_ACCESS_KEY
export S3_SECRET_KEY
export S3_PATH_STYLE
export DOCUMENT_PROCESSING_LEASE_SECONDS
export AMQP_HOST
export AMQP_PORT
export AMQP_USER
export AMQP_PASSWORD
export AMQP_VHOST
export REDIS_HOST
export REDIS_PORT
export REDIS_TIMEOUT
export LLM_ADAPTER
export LLM_BASE_URL
export LLM_API_KEY
export LLM_MODEL
export LLAMA_CPP_HF_REPO
export LLAMA_CPP_MODEL
export LLAMA_CPP_MODEL_URL
export LLAMA_CPP_CTX_SIZE
export LLAMA_CPP_PARALLEL
export LLAMA_CPP_N_PREDICT

# Current user ID and group ID except MacOS where it conflicts with Docker abilities
ifeq ($(shell uname), Darwin)
    export UID=1000
    export GID=1000
else
    export UID=$(shell id -u)
    export GID=$(shell id -g)
endif

export COMPOSE_PROJECT_NAME=${STACK_NAME}
DOCKER_COMPOSE_DEV := docker compose -f docker/compose.yml -f docker/dev/compose.yml
DOCKER_COMPOSE_DEV_ALL := $(DOCKER_COMPOSE_DEV) --profile worker --profile llm
DOCKER_COMPOSE_TEST := docker compose -f docker/compose.yml -f docker/test/compose.yml

DOCKER_COMPOSE_DEV_UP := $(DOCKER_COMPOSE_DEV)
DOCKER_COMPOSE_DEV_UP_OPTIONS := up -d --remove-orphans
ifeq ($(LLM_ADAPTER),llamacpp)
    DOCKER_COMPOSE_DEV_UP := $(DOCKER_COMPOSE_DEV_UP) --profile llm
endif
ifneq ($(QUEUE_DRIVER),)
ifneq ($(QUEUE_DRIVER),sync)
    DOCKER_COMPOSE_DEV_UP := $(DOCKER_COMPOSE_DEV_UP) --profile worker
    DOCKER_COMPOSE_DEV_UP_OPTIONS := up -d --remove-orphans --scale worker=$(WORKERS)
endif
endif

#
# Development
#

ifeq ($(PRIMARY_GOAL),build)
build: ## Build docker images.
	$(DOCKER_COMPOSE_DEV) build $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),up)
up: ## Up the dev environment.
	$(DOCKER_COMPOSE_DEV_UP) $(DOCKER_COMPOSE_DEV_UP_OPTIONS)
endif

ifeq ($(PRIMARY_GOAL),down)
down: ## Down the dev environment.
	$(DOCKER_COMPOSE_DEV_ALL) down --remove-orphans
endif

ifeq ($(PRIMARY_GOAL),stop)
stop: ## Stop the dev environment.
	$(DOCKER_COMPOSE_DEV_ALL) stop
endif

ifeq ($(PRIMARY_GOAL),clear)
clear: ## Remove development docker containers and volumes.
	$(DOCKER_COMPOSE_DEV_ALL) down --volumes --remove-orphans
endif

ifeq ($(PRIMARY_GOAL),shell)
shell: ## Get into container shell.
	$(DOCKER_COMPOSE_DEV) exec app /bin/bash
endif

#
# Tools
#

ifeq ($(PRIMARY_GOAL),yii)
yii: ## Execute Yii command.
	$(DOCKER_COMPOSE_DEV) run --rm app ./yii $(CLI_ARGS)
.PHONY: yii
endif

ifeq ($(PRIMARY_GOAL),composer)
composer: ## Run Composer.
	$(DOCKER_COMPOSE_DEV) run --rm app composer $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),rector)
rector: ## Run Rector.
	$(DOCKER_COMPOSE_DEV) run --rm app ./vendor/bin/rector $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),cs-fix)
cs-fix: ## Run PHP CS Fixer.
	$(DOCKER_COMPOSE_DEV) run --rm app ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --diff
endif

#
# Tests and analysis
#

ifeq ($(PRIMARY_GOAL),test)
test: ## Run tests.
	$(DOCKER_COMPOSE_TEST) run --rm app ./vendor/bin/codecept run $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),test-coverage)
test-coverage: ## Run tests with coverage.
	$(DOCKER_COMPOSE_TEST) run --rm app ./vendor/bin/codecept run --coverage --coverage-html --disable-coverage-php
endif

ifeq ($(PRIMARY_GOAL),codecept)
codecept: ## Run Codeception.
	$(DOCKER_COMPOSE_TEST) run --rm app ./vendor/bin/codecept $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),psalm)
psalm: ## Run Psalm.
	$(DOCKER_COMPOSE_DEV) run --rm app ./vendor/bin/psalm $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),composer-dependency-analyser)
composer-dependency-analyser: ## Run Composer Dependency Analyser.
	$(DOCKER_COMPOSE_DEV) run --rm app ./vendor/bin/composer-dependency-analyser --config=composer-dependency-analyser.php $(CLI_ARGS)
endif

#
# Production
#

ifeq ($(PRIMARY_GOAL),prod-build)
prod-build: ## Build an image.
	docker build --file docker/Dockerfile --target prod --pull -t ${IMAGE}:${IMAGE_TAG} .
endif

ifeq ($(PRIMARY_GOAL),prod-push)
prod-push: ## Push image to repository.
	docker push ${IMAGE}:${IMAGE_TAG}
endif

ifeq ($(PRIMARY_GOAL),prod-deploy)
prod-deploy: ## Deploy to production.
	set -euo pipefail \
	docker -H ${PROD_SSH} stack deploy --prune --detach=false --with-registry-auth -c docker/compose.yml -c docker/prod/compose.yml ${STACK_NAME} 2>&1 | tee deploy.log \
	if grep -qiE 'rollback:|update rolled back|service update paused' deploy.log then \
		FAILED_TASK_ID="$(grep -oiE 'task[[:space:]]+[a-z0-9]+' deploy.log | head -n 1 | awk '{print $2}')" \
		if [ -n "${FAILED_TASK_ID}" ]; then \
			echo "Docker Swarm update failed. Failed task ID: ${FAILED_TASK_ID}" \
			echo "--- docker service logs (${FAILED_TASK_ID}) ---" \
			docker -H ${PROD_SSH} service logs --timestamps --tail 500 "${FAILED_TASK_ID}" || true \
		else \
			echo 'Docker Swarm update failed. Failed task ID: not found in deploy output.' \
		fi \
		exit 1 \
	fi
endif

#
# Other
#

ifeq ($(PRIMARY_GOAL),help)
help: ## This help.
	@awk 'BEGIN { printf "\nUsage:\n  make \033[36m<target>\033[0m\n" } \
	/^#$$/ { blank = 1; next } \
	blank && /^# [a-zA-Z]/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 3); blank = 0; next } \
	/^[a-zA-Z_-]+:([^=]|$$)/ { \
		split($$0, parts, "##"); \
		target = parts[1]; sub(/:.*/, "", target); \
		desc = parts[2]; \
		gsub(/^[[:space:]]+|[[:space:]]+$$/, "", desc); \
		printf "  \033[36m%-25s\033[0m %s\n", target, desc; \
		blank = 0; \
	}' $(MAKEFILE_LIST)
endif
