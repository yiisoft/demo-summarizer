# Yii3 Document Summarizer

A Yii3 demo for uploading documents, extracting readable markdown, summarizing the content with a configurable LLM adapter, and tracking processing progress through queues.

This repository contains a runnable Yii3 document summarizer demo. The detailed implementation checklist is in [plan.md](plan.md).

## Features

- Batch upload for text, markdown, HTML, PDF, and DOCX documents.
- Server-side upload validation with size, extension, MIME, and signature checks.
- Local object storage by default, with S3-compatible storage configuration for MinIO or another compatible service.
- Queue-oriented processing with sync mode by default and AMQP/Valkey modes queued for worker processing.
- Text, Markdown, and HTML extraction through the native adapter; PDF and DOCX require the optional Kreuzberg runtime.
- Mock and Ollama summarizer adapters.
- Web UI for document status, progress, summaries, downloads, deletion, and retry.

## Local Development

Run project commands through `make` so they execute in the configured Docker environment. Do not call `./yii` or `composer` directly from the host.

Build and start the Docker development environment:

```bash
make build
make up
```

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
make yii document:migrate
```

Process queued documents when `QUEUE_DRIVER` is not `sync`:

```bash
make yii document:work
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

## Configuration

Common environment variables:

- `QUEUE_DRIVER=sync|amqp|redis`
- `DATABASE_DSN=sqlite:/app/runtime/documents.sqlite`
- `DOCUMENT_STORAGE_DRIVER=local|s3`
- `S3_ENDPOINT`, `S3_REGION`, `S3_BUCKET`, `S3_ACCESS_KEY`, `S3_SECRET_KEY`, `S3_PATH_STYLE`
- `EXTRACTOR_ADAPTER=kreuzberg|native`
- `LLM_ADAPTER=mock|ollama`
- `OLLAMA_BASE_URL`, `OLLAMA_MODEL`
