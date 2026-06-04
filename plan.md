# Yii3 Document Summarizer Queue Demo Implementation Plan

## Summary

Build a Yii3 demo that uploads multiple documents, stores metadata in SQLite, stores document blobs in S3-compatible object storage, queues one processing task per document, extracts markdown,
summarizes through a swappable LLM adapter, and shows processing progress. Support sync, RabbitMQ/AMQP, and Valkey-backed queue modes.

Queue and adapter polishing is a separate upstream workstream: implementation should identify generic queue rough edges, then fix them through focused PRs to yiisoft/queue and relevant adapters.

## Key Changes

- [ ] Add dependencies:
    - [ ] yiisoft/queue, yiisoft/queue-amqp, yiisoft/queue-redis
    - [ ] yiisoft/validator
    - [ ] yiisoft/db-migration
    - [ ] S3-compatible storage client or Flysystem S3 adapter
    - [ ] kreuzberg/kreuzberg for the preferred universal document extraction adapter

- [ ] Update Docker with pdo_sqlite, Redis extension, RabbitMQ, Valkey, S3-compatible storage such as MinIO, Kreuzberg PHP extension support, and optional worker services.
- [ ] Add config:
    - [ ] QUEUE_DRIVER=sync|amqp|redis
    - [ ] DATABASE_DSN=sqlite:@runtime/documents.sqlite
    - [ ] S3_ENDPOINT, S3_REGION, S3_BUCKET, S3_ACCESS_KEY, S3_SECRET_KEY, S3_PATH_STYLE
    - [ ] DOCUMENT_PROCESSING_LEASE_SECONDS=900
    - [ ] EXTRACTOR_ADAPTER=kreuzberg|native
    - [ ] LLM_ADAPTER=mock|ollama
    - [ ] OLLAMA_BASE_URL, OLLAMA_MODEL
    - [ ] reserved future vars: LLM_PROVIDER, LLM_API_KEY, LLM_MODEL

- [ ] Add Yii migrations for:
    - [ ] documents: file metadata, generated object key, status, progress, processing lease, markdown object key, summary, short error, detailed error, retry metadata, timestamps
    - [ ] processing_events: document id, event type, message, progress, timestamp

- [ ] Add web UI:
    - [ ] upload .md, .txt, .html, .pdf, .docx
    - [ ] max 20 MB per file, 100 MB per batch
    - [ ] validate uploads with yiisoft/validator, including size, extension, and server-side MIME/signature checks
    - [ ] document list, status/progress, detail page with summary
    - [ ] original download and markdown view/download by document ID
    - [ ] poll status every 2 seconds while documents are active
    - [ ] manual retry for failed documents
    - [ ] keep failed documents available for retry; deletion is an explicit user action, not automatic failure handling

- [ ] Add queue processing:
    - [ ] queue message payload is only documentId
    - [ ] handler reloads document state from SQLite
    - [ ] handler uses app-level claim/lease so multiple workers can run safely
    - [ ] handler transitions through uploaded, queued, extracting, summarizing, completed, failed
    - [ ] unsupported formats fail only that document
    - [ ] retries are available from the regular web UI
    - [ ] failed documents stay in storage until retried, explicitly deleted, or replaced by a successful retry
    - [ ] reprocessing overwrites markdown/summary and appends events
    - [ ] stale temporary objects and obsolete successful retry artifacts are cleaned during regular processing

- [ ] Add extraction:
    - [ ] .md and .txt: read as text/markdown
    - [ ] implement an ExtractorInterface with a preferred KreuzbergExtractor adapter using kreuzberg/kreuzberg
    - [ ] use Kreuzberg for .html, .pdf, .docx, and other supported document formats when available
    - [ ] normalize Kreuzberg output to markdown-like content suitable for summarization
    - [ ] keep native per-format extraction as a fallback adapter only if Kreuzberg format support or extraction quality is not acceptable for a required format

- [ ] Add LLM abstraction:
    - [ ] chunk-ready SummarizerInterface
    - [ ] deterministic MockSummarizer that summarizes a configured first slice
    - [ ] OllamaSummarizer adapter with configurable base URL and model
    - [ ] DI binding ready for additional adapters later

- [ ] Add observability:
    - [ ] app-level progress and event timeline from SQLite
    - [ ] read queue/broker progress, depth, or status where the selected queue adapter exposes it
    - [ ] fall back to database progress when broker-level state is unavailable or adapter-specific
    - [ ] enqueue/start/finish/failure timestamps
    - [ ] short user-facing errors and detailed internal error events

- [ ] Add CLI/docs:
    - [ ] Yii migration commands
    - [ ] sync, RabbitMQ/AMQP, and Valkey-backed Redis-protocol modes
    - [ ] worker startup
    - [ ] first-run and smoke-test flow
    - [ ] S3/MinIO, RabbitMQ, Valkey, Kreuzberg/native extractor, and Ollama setup

## Upstream Queue Work

- [ ] Clone sibling repositories:
    - [ ] /home/samdark/src/queue
    - [ ] /home/samdark/src/queue-amqp
    - [ ] /home/samdark/src/queue-redis

- [ ] Use Composer path repositories while developing the demo.
- [ ] Assess yiisoft/queue architecture while implementing: driver abstractions, message lifecycle, acknowledgement/retry semantics, delayed jobs, visibility/lease behavior, serialization, worker commands, observability hooks, and extension points.
- [ ] Assess adapter issues in yiisoft/queue-amqp and yiisoft/queue-redis: RabbitMQ compatibility, Valkey compatibility through Redis protocol, connection/config ergonomics, failure handling, queue depth/status access, tests, and documentation.
- [ ] Record bugs, missing features, and adapter limitations encountered during demo implementation.
- [ ] Create focused upstream PRs only for generic queue/adaptor usage, config, command, docs, or test issues.
- [ ] Each upstream PR should include a reproduction test, minimal fix, and directly related docs/config updates.
- [ ] Release affected queue packages only after focused PRs are merged and package tests pass.

## Test Plan

- [ ] Default tests:
    - [ ] functional tests for upload, list, detail, status polling, downloads, delete, and manual retry
    - [ ] unit tests for repositories, migrations, status transitions, processing leases, event recording, extraction, mock summarization, Ollama adapter request handling, S3 storage, and handler success/failure
    - [ ] sync-mode queue processing tests

- [ ] Opt-in/local integration tests:
    - [ ] RabbitMQ/AMQP queue mode
    - [ ] Valkey-backed Redis-protocol queue mode
    - [ ] S3-compatible storage via MinIO
    - [ ] Kreuzberg PHP extraction adapter
    - [ ] native fallback extractor only if fallback support is implemented
    - [ ] Ollama adapter against a local Ollama service when available

- [ ] Acceptance smoke path:
    - [ ] install dependencies
    - [ ] run migrations
    - [ ] upload 3 mixed supported files
    - [ ] see queued/progress events
    - [ ] process successfully in sync, AMQP, and Valkey modes
    - [ ] verify summaries and extracted markdown
    - [ ] force one failure, verify the failed document remains available, and retry it through the UI

- [ ] Quality checks:
    - [ ] composer test
    - [ ] Psalm
    - [ ] composer dependency analyser
    - [ ] upstream package tests for every queue package changed

## Assumptions

- [ ] SQLite is the selected persistence layer.
- [ ] S3-compatible object storage is the selected document storage layer; local filesystem storage is not used for durable document blobs.
- [ ] The first LLM implementations are mock and Ollama.
- [ ] Kreuzberg through the kreuzberg/kreuzberg PHP package is the preferred universal extractor because Docker owns the PHP extension/runtime dependencies.
- [ ] Valkey is the Docker key-value store; yiisoft/queue-redis may still be used through the Redis protocol if compatible.
- [ ] Uploaded files use generated storage names; original names are display-only.
- [ ] Queue observability v1 should use broker/adapter state where practical and database progress as the portable fallback.
