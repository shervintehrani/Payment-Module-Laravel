# Shortcuts for the Docker workflow. Run `make help` to list them.
.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

test: ## Run the test suite in Docker
	docker compose run --rm app

shell: ## Open a shell in the dev container
	docker compose run --rm --entrypoint sh app

demo: ## Start the demo app on http://localhost:8000/payment
	docker compose up --build demo

clean: ## Stop containers and remove volumes
	docker compose down -v

.PHONY: help test shell demo clean
