# Yii3 Document Summarizer

A Yii3 demo for uploading documents, extracting readable markdown, summarizing the content with a configurable LLM adapter, and tracking processing progress through queues.

This repository contains a runnable Yii3 document summarizer demo. The detailed implementation checklist is in [plan.md](plan.md).

## Features

- Batch upload for text, markdown, HTML, PDF, and DOCX documents.
- Server-side upload validation with size, extension, MIME, and signature checks.
- S3-compatible object storage by default, with Garage wired for local development.
- Queue-oriented processing through `yiisoft/queue`, with sync mode by default.
- AMQP and Valkey queue modes use `yiisoft/queue` adapters through local Composer path repositories while upstream compatibility fixes are prepared.
- Preferred extraction through the Docker-installed Kreuzberg CLI, with a native fallback for text, Markdown, and HTML.
- Mock and Ollama summarizer adapters.
- Web UI for document status, progress, summaries, downloads, deletion, and retry.

## Local Development

Run project commands through `make` so they execute in the configured Docker environment. Do not call `./yii` or `composer` directly from the host.

Build and start the Docker development environment:

```bash
make build
make up
```

Garage is the local S3-compatible storage service for the demo; MinIO is not required. The `documents` bucket is created automatically.

The Docker image installs a pinned Kreuzberg CLI runtime for PDF, DOCX, HTML, and other supported document extraction. Rebuild the image after Dockerfile or extractor runtime changes.

Run Composer commands inside Docker:

```bash
make composer install
```

Run Yii console commands:

```bash
make yii <command>
```

Create the SQLite tables:

```bash
make -- yii migrate:up -y
```

Process queued messages with the native Yii queue worker:

```bash
make yii queue:run
make yii queue:listen
```

Do not use a demo-specific worker command; document processing is wired through `yiisoft/queue`.

Run non-sync queue modes by selecting the queue driver and using the same native worker commands:

```bash
QUEUE_DRIVER=amqp make up
QUEUE_DRIVER=amqp make -- yii queue:run

QUEUE_DRIVER=redis make up
QUEUE_DRIVER=redis make -- yii queue:run
```

Open a shell in the app container:

```bash
make shell
```

Stop the environment:

```bash
make down
```

## Quality Checks

Run the test suite:

```bash
make test
```

Run static analysis:

```bash
make psalm
make composer-dependency-analyser
```

## Implementation Plan

See [plan.md](plan.md) for planned architecture, dependencies, queue work, extractor strategy, test coverage, and assumptions.

The demo should use Yii3 packages and native Yii commands where they exist. App-specific code is reserved for demo domain behavior, adapter selection, and documented package compatibility gaps.

## Configuration

Common environment variables:

- `QUEUE_DRIVER=sync|amqp|redis`
- `QUEUE_NAME`
- `AMQP_HOST`, `AMQP_PORT`, `AMQP_USER`, `AMQP_PASSWORD`, `AMQP_VHOST`
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_TIMEOUT`
- `DATABASE_DSN=sqlite:/app/runtime/documents.sqlite`
- `DOCUMENT_STORAGE_DRIVER=s3|local`
- `S3_ENDPOINT`, `S3_REGION`, `S3_BUCKET`, `S3_ACCESS_KEY`, `S3_SECRET_KEY`, `S3_PATH_STYLE`
- `EXTRACTOR_ADAPTER=kreuzberg|native`
- `LLM_ADAPTER=mock|ollama`
- `OLLAMA_BASE_URL`, `OLLAMA_MODEL`

The demo currently points Composer at sibling `yiisoft/queue`, `yiisoft/queue-amqp`, and `yiisoft/queue-redis` repositories so AMQP and Valkey can run against the current Yii queue core while upstream fixes are prepared.
