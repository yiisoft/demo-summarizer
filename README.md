# Yii3 Document Summarizer

A runnable Yii3 demo for uploading documents, extracting readable markdown, summarizing content, and tracking processing progress.

## What It Does

- Upload one or more Markdown, text, HTML, PDF, or DOCX files.
- Validate uploads before processing.
- Store original files and extracted markdown in S3-compatible storage.
- Process documents through `yiisoft/queue`.
- Show status, progress, summaries, extracted markdown, original downloads, retries, deletion, and a full clear action.
- Use a deterministic mock summarizer by default, or connect to Ollama running on the host.

## Requirements

- Docker with Docker Compose.
- `make`.
- Optional: Ollama on the host if you want real LLM summaries instead of mock summaries.

## Quick Start

Build and start the demo:

```bash
make build
make up
```

Create the database tables:

```bash
make -- yii migrate:up -y
```

Open the app:

```text
http://127.0.0.1/
```

Upload supported documents from the upload box. The app shows progress and results in the document table. Use **Retry** for failed documents, **Delete** for one document, or **Clear all** to remove all documents, stored files, database records, and pending queue jobs.

Stop the demo:

```bash
make down
```

## Running Commands

Run project commands through `make` so they execute in the configured Docker environment.

Do not call `./yii` or `composer` directly from the host.

Run Composer commands:

```bash
make composer install
```

Run Yii console commands:

```bash
make yii <command>
```

When passing command options that start with `-`, use `make --`:

```bash
make -- yii migrate:up -y
```

Open a shell in the app container:

```bash
make shell
```

## Queue Modes

The default queue mode is `sync`, so uploaded documents are processed immediately.

To process jobs with the native Yii queue worker:

```bash
make yii queue:run
make yii queue:listen
```

To run AMQP mode:

```bash
QUEUE_DRIVER=amqp make up
QUEUE_DRIVER=amqp make -- yii queue:run
```

To run Valkey/Redis mode:

```bash
QUEUE_DRIVER=redis make up
QUEUE_DRIVER=redis make -- yii queue:run
```

Document processing is wired through `yiisoft/queue`; there is no demo-specific worker command.

## Storage

Garage is included as the local S3-compatible storage service. The `documents` bucket is created automatically.

Local filesystem storage is available for testing with:

```bash
DOCUMENT_STORAGE_DRIVER=local
```

## Ollama

The mock summarizer is used by default.

To use Ollama, run Ollama on the host and configure:

```bash
LLM_ADAPTER=ollama
OLLAMA_BASE_URL=http://host.docker.internal:11434
OLLAMA_MODEL=llama3.2
```

On Linux, host Ollama must listen on an address Docker containers can reach. For example:

```bash
OLLAMA_HOST=0.0.0.0:11434 ollama serve
```

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
- `OLLAMA_BASE_URL`
- `OLLAMA_MODEL`

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

## Maintainer Notes

The implementation checklist and architecture notes are in [plan.md](plan.md). The demo should use Yii3 packages and native Yii commands where they exist.
