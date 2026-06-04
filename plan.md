# Yii3 Document Summarizer Queue Demo Implementation Plan

## Summary

Build a Yii3 demo that uploads multiple documents, stores metadata in SQLite, stores document blobs in S3-compatible object storage, queues one processing task per document, extracts markdown,
summarizes through a swappable LLM adapter, and shows processing progress. Support sync, RabbitMQ/AMQP, and Valkey-backed queue modes.

Queue and adapter polishing is a separate upstream workstream: implementation should identify generic queue rough edges, then fix them through focused PRs to yiisoft/queue and relevant adapters.

## Key Changes

- [x] Add dependencies:
    - [x] yiisoft/queue, yiisoft/queue-amqp, yiisoft/queue-redis
    - [x] yiisoft/validator
    - [x] yiisoft/db-migration
    - [x] S3-compatible storage client or Flysystem S3 adapter
    - [x] pinned Kreuzberg CLI runtime in Docker for the preferred universal document extraction adapter

- [x] Update Docker with pdo_sqlite, Redis extension, RabbitMQ, Valkey, S3-compatible storage through Garage, pinned Kreuzberg CLI support, optional worker services, and an optional `llama.cpp` local LLM service.
- [x] Add config:
    - [x] QUEUE_DRIVER=sync|amqp|redis
    - [x] DATABASE_DSN=sqlite:@runtime/documents.sqlite
    - [x] S3_ENDPOINT, S3_REGION, S3_BUCKET, S3_ACCESS_KEY, S3_SECRET_KEY, S3_PATH_STYLE
    - [x] DOCUMENT_PROCESSING_LEASE_SECONDS=900
    - [x] EXTRACTOR_ADAPTER=kreuzberg|native
    - [x] LLM_ADAPTER=llamacpp|mock
    - [x] LLAMA_CPP_SERVICE=0|1 controls whether the heavy Docker `llama.cpp` service starts
    - [x] LLM_BASE_URL, LLM_API_KEY, LLM_MODEL
    - [x] LLAMA_CPP_HF_REPO, LLAMA_CPP_MODEL, LLAMA_CPP_MODEL_URL
    - [x] LLAMA_CPP_CTX_SIZE, LLAMA_CPP_PARALLEL, LLAMA_CPP_N_PREDICT
    - [x] default `llama.cpp` model is `ggml-org/gemma-3-1b-it-GGUF:Q4_K_M`, the smallest Gemma default that gives usable demo summaries on CPU-only hardware
    - [x] default `llama.cpp` context is one 4096-token slot so regular demo documents fit
    - [x] Gemma 3 local summaries run with the `llama.cpp` Jinja chat-template mode enabled

- [x] Add Yii migrations for:
    - [x] documents: file metadata, generated object key, status, progress, processing lease, markdown object key, summary, short error, detailed error, retry metadata, timestamps
    - [x] processing_events: document id, event type, message, progress, timestamp
    - [x] native `yiisoft/db-migration` commands are used instead of a demo-specific migration command

- [x] Add web UI:
    - [x] upload .md, .txt, .html, .pdf, .docx
    - [x] max 20 MB per file, 100 MB per batch
    - [x] validate uploads with yiisoft/validator, including size, extension, and server-side MIME/signature checks
    - [x] document list, status/progress, detail page with summary
    - [x] original download and markdown view/download by document ID
    - [x] poll status every 500ms while documents are active
    - [x] manual retry for failed documents
    - [x] keep failed documents available for retry; deletion is an explicit user action, not automatic failure handling

- [x] Add queue processing:
    - [x] queue message payload is only documentId
    - [x] sync-mode processing pushes `SummarizeDocumentMessage` through `yiisoft/queue`
    - [x] handler reloads document state from SQLite
    - [x] handler uses app-level claim/lease so multiple workers can run safely
    - [x] handler transitions through uploaded, queued, extracting, summarizing, completed, failed
    - [x] unsupported formats fail only that document
    - [x] retries are available from the regular web UI
    - [x] failed documents stay in storage until retried, explicitly deleted, or replaced by a successful retry
    - [x] reprocessing overwrites markdown/summary and appends events
    - [x] stale temporary objects and obsolete successful retry artifacts are cleaned during regular processing

- [x] Add extraction:
    - [x] .md and .txt: read as text/markdown
    - [x] implement an ExtractorInterface with a preferred KreuzbergExtractor adapter using the Docker-installed Kreuzberg CLI
    - [x] use Kreuzberg for .html, .pdf, .docx, and other supported document formats when available
    - [x] normalize Kreuzberg output to markdown-like content suitable for summarization
    - [x] keep native per-format extraction as a fallback adapter only if Kreuzberg format support or extraction quality is not acceptable for a required format

- [x] Add LLM abstraction:
    - [x] chunk-ready SummarizerInterface
    - [x] deterministic MockSummarizer that summarizes a configured first slice
    - [x] OpenAI-compatible adapter with configurable base URL, model, and API key
    - [x] DI binding ready for additional adapters later

- [x] Add observability:
    - [x] app-level progress and event timeline from SQLite
    - [x] record adapter compatibility blockers where broker-level queue depth or status is not available through compatible Yii queue packages
    - [x] fall back to database progress when broker-level state is unavailable or adapter-specific
    - [x] enqueue/start/finish/failure timestamps
    - [x] short user-facing errors and detailed internal error events

- [x] Add CLI/docs:
    - [x] native Yii migration commands
    - [x] sync queue mode
    - [x] native `yiisoft/queue` worker startup with `queue:run` and `queue:listen`
    - [x] non-sync queue modes start a background queue worker through `QUEUE_DRIVER=<driver> make up`
    - [x] non-sync queue modes can scale background workers through `WORKERS=<count> make up`
    - [x] default development mode uses AMQP, two workers, and Kreuzberg extraction
    - [x] default development mode uses mock summaries and does not start the heavy Docker `llama.cpp` service
    - [x] app and worker startup waits for the Docker `llama.cpp` health endpoint in local LLM mode
    - [x] RabbitMQ/AMQP and Valkey-backed Redis-protocol adapter path-repository compatibility work
    - [x] first-run and smoke-test flow
    - [x] S3/Garage, RabbitMQ, Valkey, Kreuzberg/native extractor, and local `llama.cpp` setup
    - [x] document that project commands must run through `make`, not direct host `./yii` or `composer`

## Upstream Queue Work

- [x] Clone sibling repositories:
    - [x] /home/samdark/src/queue
    - [x] /home/samdark/src/queue-amqp
    - [x] /home/samdark/src/queue-redis

- [x] Use Composer path repositories while developing the demo.
- [x] Assess yiisoft/queue architecture while implementing: driver abstractions, message lifecycle, acknowledgement/retry semantics, delayed jobs, visibility/lease behavior, serialization, worker commands, observability hooks, and extension points.
- [x] Assess adapter issues in yiisoft/queue-amqp and yiisoft/queue-redis: RabbitMQ compatibility, Valkey compatibility through Redis protocol, connection/config ergonomics, failure handling, queue depth/status access, tests, and documentation.
- [x] Record bugs, missing features, and adapter limitations encountered during demo implementation.
- [x] Commit focused local upstream changes:
    - [x] yiisoft/queue-amqp `de0064d` and `277b975` on `current-core-compat`
    - [x] yiisoft/queue-redis `8218187`, `954f917`, `7b8f947`, `0b7acff`, and `111568b` on `current-core-compat`
- [x] Create focused upstream PRs only for generic queue/adaptor usage, config, command, docs, or test issues.
- [x] Each upstream change should include a reproduction test, minimal fix, and directly related docs/config updates.
- [ ] Release affected queue packages only after focused PRs are merged and package tests pass.

## Test Plan

- [x] Default tests:
    - [x] functional tests for upload, list, detail, status endpoint, downloads, delete, and manual retry
    - [x] unit workflow tests for schema creation, repository transitions, local storage, processor success, and processor failure
    - [x] unit queue tests for sync `yiisoft/queue` push and Yii queue envelope handling
    - [x] unit tests for repositories, migrations, status transitions, processing leases, event recording, extraction, mock summarization, and handler success/failure
    - [x] unit tests for OpenAI-compatible adapter request handling and S3 storage
    - [x] sync-mode queue processing tests

- [ ] Opt-in/local integration tests:
    - [x] RabbitMQ/AMQP queue mode
    - [x] Valkey-backed Redis-protocol queue mode
    - [x] S3-compatible storage via Garage
    - [x] Kreuzberg CLI extraction adapter
    - [x] native fallback extractor only if fallback support is implemented
    - [ ] `llama.cpp` adapter against the Docker `llama.cpp` service when model downloads are available

- [x] Acceptance smoke path:
    - [x] install dependencies
    - [x] run migrations
    - [x] upload mixed supported files including PDF and DOCX
    - [x] see queued/progress events
    - [x] process successfully in sync, AMQP, and Valkey modes
    - [x] process successfully in sync mode
    - [x] verify summaries and extracted markdown
    - [x] force one failure, verify the failed document remains available, and retry it through the UI

- [x] Quality checks:
    - [x] make test
    - [x] make psalm
    - [x] make composer-dependency-analyser
    - [x] upstream yiisoft/queue-amqp package tests
    - [x] upstream yiisoft/queue-redis package tests

## Assumptions

- [x] SQLite is the selected persistence layer.
- [x] Prefer Yii3 packages, commands, interfaces, and adapters over demo-specific implementations when Yii3 provides the required behavior.
- [x] S3-compatible object storage is the selected document storage layer; local filesystem storage is not used for durable document blobs.
- [x] The first LLM implementations are mock and OpenAI-compatible local LLM summaries through `llama.cpp`.
- [x] Kreuzberg through the Docker-installed CLI is the preferred universal extractor because Docker owns the extraction runtime dependency.
- [x] Valkey is the Docker key-value store; yiisoft/queue-redis may still be used through the Redis protocol if compatible.
- [x] Uploaded files use generated storage names; original names are display-only.
- [x] Queue observability v1 should use broker/adapter state where practical and database progress as the portable fallback.
