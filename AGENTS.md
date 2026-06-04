# Repository Guidelines

- Run project commands through the Makefile so they execute in the configured Docker environment.
- Do not call `./yii` or `composer` directly from the host. Use `make yii ...`, `make composer ...`, `make test`, `make psalm`, and `make composer-dependency-analyser`.
- Use `make build` after Dockerfile or PHP extension changes before running Docker-backed checks.
