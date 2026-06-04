# Upstream Queue Notes

## Local upstream compatibility work

During demo implementation on 2026-06-04, the `yiisoft/queue-amqp` and `yiisoft/queue-redis` adapter sources were checked against the current `yiisoft/queue` core package.

Local sibling repositories were cloned under `/home/samdark/src` and the demo uses Composer path repositories for:

- `/home/samdark/src/queue`
- `/home/samdark/src/queue-amqp`
- `/home/samdark/src/queue-redis`

## Issues found and fixed locally

- `AdapterInterface::status()` now returns `Yiisoft\Queue\MessageStatus`; both adapters still referenced the removed `Yiisoft\Queue\JobStatus`.
- Adapter package tests used older core names such as `MiddlewareFactoryConsume`, `MiddlewareFactoryFailure`, `MiddlewareFactoryPush`, `QueueInterface::DEFAULT_CHANNEL`, `Queue::withAdapter()`, concrete `Yiisoft\Queue\Message\Message`, and `JobFailureException`.
- AMQP delay middleware used the old push middleware API where middleware could replace the adapter. Current `yiisoft/queue` push middleware works on `MessageInterface`; AMQP delay now wraps messages in `DelayEnvelope`, and the AMQP adapter translates that metadata into delayed RabbitMQ queue settings.
- Redis package tests used the old `docker-compose` binary; the local Makefile now uses `docker compose`.
- Redis' adapter-specific message class implemented the old message interface and was aligned with current `getType()` and `withMetadata()` requirements.

## Demo impact

- The demo uses `yiisoft/queue` for sync processing and native Yii `queue:run` / `queue:listen` commands for AMQP and Valkey modes.
- AMQP status remains unsupported by the adapter, so the demo uses SQLite document status/progress as the portable status source.
- Redis status works through the adapter after the `MessageStatus` alignment, but the demo still uses database progress for user-facing document workflow status.
- No demo-specific worker command or direct broker transport is used.

## Verified locally

- `yiisoft/queue-amqp`: `make test v=82` passed with 20 tests and 81 assertions.
- `yiisoft/queue-redis`: `make test` passed with 22 tests and 44 assertions.
- Demo AMQP and Valkey queue modes processed uploaded README documents successfully through native `queue:run`.

## Local commits

- `/home/samdark/src/queue-amqp`: `de0064d` (`current-core-compat`)
- `/home/samdark/src/queue-redis`: `8218187`, `954f917`, `7b8f947`, `0b7acff` (`current-core-compat`)

## Upstream PRs

- `yiisoft/queue-amqp`: https://github.com/yiisoft/queue-amqp/pull/127
- `yiisoft/queue-redis`: https://github.com/yiisoft/queue-redis/pull/12

Package releases remain pending until the focused PRs are merged and package checks pass.
