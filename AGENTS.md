# Repository Guidelines

- Run project commands through the Makefile so they execute in the configured Docker environment.
- Do not call `./yii` or `composer` directly from the host. Use `make yii ...`, `make composer ...`, `make test`, `make psalm`, and `make composer-dependency-analyser`.
- When passing command options that start with `-`, use `make -- ...` so Make does not consume them. Example: `make -- yii migrate:up -y`.
- Use `make build` after Dockerfile, PHP extension, or Kreuzberg extractor runtime changes before running Docker-backed checks.
- Prefer existing Yii3 packages, commands, interfaces, and adapters over custom implementations. Do not add an app-specific replacement for functionality that exists in Yii3 unless the Yii3 package is documented as unavailable or incompatible for this demo.
- Use host Ollama for local LLM integration. Do not add an Ollama service or volume to Docker Compose; containers should connect to the host Ollama endpoint instead.
