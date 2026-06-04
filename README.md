# Yii3 Document Summarizer

A runnable Yii3 demo for uploading documents, extracting readable markdown, summarizing content, and tracking processing progress.

## What It Does

- Upload one or more Markdown, text, HTML, PDF, or DOCX files.
- Validate uploads before processing.
- Store original files and extracted markdown in S3-compatible storage.
- Process documents through `yiisoft/queue`.
- Show status, progress, summaries, extracted markdown, original downloads, retries, deletion, and a full clear action.
- Use deterministic mock summaries by default, with optional local summaries through the included `llama.cpp` Docker service.

## Requirements

- Docker with Docker Compose.
- `make`.

## Quick Start

Build and start the demo:

```bash
make build
make up
```

By default, development uses AMQP queue mode, two background workers, Kreuzberg extraction, and mock summaries. Copy `.env.example` to `.env` to adjust these settings.

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

The default development queue mode is `amqp`, so `make up` starts the app and two background workers.

For immediate in-request processing without workers, use sync mode:

```bash
QUEUE_DRIVER=sync make up
```

To process jobs with the native Yii queue worker:

```bash
make yii queue:run
make yii queue:listen
```

To run AMQP mode:

```bash
QUEUE_DRIVER=amqp make up
```

To run Valkey/Redis mode:

```bash
QUEUE_DRIVER=redis make up
```

Any explicit non-`sync` queue driver starts the native Yii queue worker in the background.

Run multiple background workers by setting `WORKERS`:

```bash
QUEUE_DRIVER=amqp WORKERS=3 make up
QUEUE_DRIVER=redis WORKERS=3 make up
```

Document processing is wired through `yiisoft/queue`; there is no demo-specific worker command.

## Storage

By default, development uses S3-compatible storage through the included Garage container. `make up` starts Garage, and the app stores document files in the automatically created `documents` bucket.

Default Garage settings:

```text
DOCUMENT_STORAGE_DRIVER=s3
S3_ENDPOINT=http://garage:3900
S3_REGION=garage
S3_BUCKET=documents
S3_ACCESS_KEY=GKdemo000000000000000000000000000000
S3_SECRET_KEY=garage-demo-secret-key-000000000000000000000000000000
S3_PATH_STYLE=true
```

To use another S3-compatible service, set the same `S3_*` variables in `docker/dev/override.env`, then restart the demo:

```bash
make up
```

Local filesystem storage is available for testing with:

```bash
DOCUMENT_STORAGE_DRIVER=local
```

## Local LLM Summaries

Mock summaries are enabled by default so the heavy local LLM container is not started unless you ask for it.

To use local LLM summaries through the OpenAI-compatible `llama.cpp` adapter, set this in `.env`:

```bash
LLM_ADAPTER=llamacpp
LLAMA_CPP_SERVICE=1
LLM_BASE_URL=http://llama:8080/v1
LLM_MODEL=gemma-3-1b-it-Q4_K_M
LLAMA_CPP_HF_REPO=ggml-org/gemma-3-1b-it-GGUF:Q4_K_M
```

Then start the demo:

```bash
make up
```

`make up` starts the `llama.cpp` server profile only when `LLM_ADAPTER=llamacpp` and `LLAMA_CPP_SERVICE=1`. The service uses the official `ghcr.io/ggml-org/llama.cpp:server` image and is available to the app inside Docker at `http://llama:8080/v1`. The app and queue workers wait for the `llama.cpp` health endpoint before starting, so uploads do not race the model server on first boot.

The default `llama.cpp` model is `ggml-org/gemma-3-1b-it-GGUF:Q4_K_M`. It is the smallest Gemma default that gives usable summaries for this demo while still running on CPU-only hardware.
The default server uses one request slot and a 4096-token context so document summaries do not fail from the tiny model's context being split across parallel slots.

For deterministic mock summaries without the `llama.cpp` service, set this in `.env`:

```dotenv
LLM_ADAPTER=mock
LLAMA_CPP_SERVICE=0
```

By default, `llama.cpp` downloads the configured Hugging Face GGUF repository into a Docker volume. You can also mount a local model by placing a GGUF file under `models/` and setting:

```bash
LLM_ADAPTER=llamacpp
LLAMA_CPP_SERVICE=1
LLAMA_CPP_HF_REPO=
LLAMA_CPP_MODEL=/models/model.gguf
```

The application talks to any OpenAI-compatible chat completions endpoint through `LLM_BASE_URL`, `LLM_MODEL`, and optional `LLM_API_KEY`. For an external endpoint, use `LLM_ADAPTER=llamacpp` and leave `LLAMA_CPP_SERVICE=0`.

## Configuration

Common environment variables:

- `QUEUE_DRIVER=sync|amqp|redis`
- `WORKERS`
- `QUEUE_NAME`
- `AMQP_HOST`, `AMQP_PORT`, `AMQP_USER`, `AMQP_PASSWORD`, `AMQP_VHOST`
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_TIMEOUT`
- `DATABASE_DSN=sqlite:/app/runtime/documents.sqlite`
- `DOCUMENT_STORAGE_DRIVER=s3|local`
- `S3_ENDPOINT`, `S3_REGION`, `S3_BUCKET`, `S3_ACCESS_KEY`, `S3_SECRET_KEY`, `S3_PATH_STYLE`
- `EXTRACTOR_ADAPTER=kreuzberg|native`
- `LLM_ADAPTER=mock|llamacpp`
- `LLM_BASE_URL`, `LLM_MODEL`, `LLM_API_KEY`
- `LLAMA_CPP_SERVICE=0|1`
- `LLAMA_CPP_HF_REPO`, `LLAMA_CPP_MODEL`, `LLAMA_CPP_MODEL_URL`
- `LLAMA_CPP_CTX_SIZE`, `LLAMA_CPP_PARALLEL`, `LLAMA_CPP_N_PREDICT`

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
