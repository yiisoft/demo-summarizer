# Yii3 Document Summarizer

A Yii3 demo for uploading documents, extracting readable markdown, summarizing the content with a configurable LLM adapter, and tracking processing progress through queues.

This repository is currently in the planning/demo stage. The detailed implementation plan is in [plan.md](plan.md).

## Planned Features

- Batch upload for text, markdown, HTML, PDF, and DOCX documents.
- Server-side upload validation with size, extension, MIME, and signature checks.
- S3-compatible object storage for uploaded documents and extracted markdown.
- Queue-backed processing with sync, RabbitMQ/AMQP, and Valkey-backed modes.
- Document extraction through the `kreuzberg/kreuzberg` PHP package.
- Mock and Ollama summarizer adapters.
- Web UI for document status, progress, summaries, downloads, deletion, and retry.

## Local Development

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
make yii
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
