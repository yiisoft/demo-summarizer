# Upstream Queue Notes

## Adapter status type mismatch

During demo implementation on 2026-06-04, the installed `yiisoft/queue-amqp` and `yiisoft/queue-redis` adapter sources were checked against the installed `yiisoft/queue` core package.

Observed state:

- `yiisoft/queue` defines `Yiisoft\Queue\MessageStatus` and `AdapterInterface::status()` requires that return type.
- `yiisoft/queue-amqp/src/Adapter.php` imports and returns `Yiisoft\Queue\JobStatus`.
- `yiisoft/queue-redis/src/Adapter.php` imports and returns `Yiisoft\Queue\JobStatus`.
- No `Yiisoft\Queue\JobStatus` type exists in the installed queue core package.

Impact:

- Redis broker status is intended to be available through the adapter, but this mismatch must be fixed upstream before the app can safely rely on the adapter type contract.
- AMQP status is explicitly unsupported by the adapter even after the type mismatch is fixed, so the demo should keep using database progress as the portable fallback for AMQP.
- The demo uses `yiisoft/queue` for sync processing and native Yii queue commands for workers. It should not add a demo-specific worker or direct broker transport to work around this package compatibility issue.

Expected upstream direction:

- Align adapter `status()` return types and imports with `Yiisoft\Queue\MessageStatus`.
- Add compatibility tests in each adapter package that instantiate the adapter against the current queue core interface.
