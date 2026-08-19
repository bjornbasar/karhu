# karhu-queue

A minimal queue and worker abstraction, shipping with a database driver.

[github.com/bjornbasar/karhu-queue](https://github.com/bjornbasar/karhu-queue) · v0.3.0 · MIT

```bash
composer require bjornbasar/karhu-queue bjornbasar/karhu-db
```

---

## Pushing jobs

```php
use Karhu\Db\Connection;
use Karhu\Queue\DatabaseQueue;

$queue = new DatabaseQueue(new Connection('pgsql:host=localhost;dbname=myapp', 'user', 'pass'));

$queue->push('SendEmail', ['to' => 'bjorn@example.com', 'subject' => 'Hello']);
$queue->push('SendEmail', ['to' => '...'], queue: 'mail');   // a named queue
```

## Processing them

```php
use Karhu\Queue\Worker;

$worker = new Worker($queue);

$worker->register('SendEmail', function (array $data): void {
    mail($data['to'], $data['subject'], 'Body here');
});

$worker->run();      // loops until stop() is called
```

| `Worker` method | Returns | Description |
|---|---|---|
| `register(string $job, callable $handler)` | `void` | Map a job name to a handler. |
| `run()` | `void` | Loop, processing jobs until stopped. |
| `processNext()` | `bool` | Process one job. `false` when the queue is empty. |
| `stop()` | `void` | Ask `run()` to exit after the current job. |

`processNext()` is what you want in a cron-driven setup or in tests — it does one unit of work and
returns.

## `QueueInterface`

| Method | Returns | Description |
|---|---|---|
| `push(string $job, array $data = [], string $queue = 'default')` | `void` | Enqueue. |
| `pop(string $queue = 'default')` | `?array` | Claim the next job, or `null`. |
| `complete(string\|int $id)` | `void` | Mark done. |
| `fail(string\|int $id, string $reason = '')` | `void` | Mark failed, recording the reason. |
| `unstick(int $thresholdSeconds, ?string $queue = null)` | `int` | Reset jobs stuck in `processing`. Returns the count. |

Implement it for Redis, SQS or RabbitMQ — nothing else in the package assumes the database driver.

## Schema

```sql
CREATE TABLE jobs (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    queue      VARCHAR(50)  NOT NULL DEFAULT 'default',
    job        VARCHAR(255) NOT NULL,
    data       TEXT         NOT NULL DEFAULT '{}',
    status     VARCHAR(20)  NOT NULL DEFAULT 'pending',
    error      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

`updated_at` tracks the **last status transition**, which is what stuck-job recovery keys off.
`complete()` and `fail()` are guarded by `WHERE id = :id AND status = 'processing'`, so a row that
has already been reset is never flipped by a stale handler.

---

## Stuck jobs

A worker killed mid-job — OOM, host reboot, `docker kill` — leaves its row in `processing`
forever. `Worker`'s try/catch catches handler *exceptions*, not process death. `unstick()` is the
recovery path; run it on a cron:

```php
$reset = $queue->unstick(300);           // reset rows stuck >5 minutes
$reset = $queue->unstick(300, 'mail');   // scoped to one queue
```

There is no race against a live worker: the `UPDATE`'s `WHERE status='processing' AND updated_at <
cutoff` *is* the deduplication. A worker that completes a row in the meantime has already set
`status='completed'`, so the row no longer matches.

!!! warning "Handlers must be idempotent"
    "Stuck" means *no status transition in N seconds*, **not** *definitely dead*. A slow but
    perfectly alive handler that exceeds the threshold **will** be unstuck and re-popped while it
    is still running. Set the threshold well above your slowest handler's wall time — a 5×
    safety factor is the recommendation — and make handlers safe to run twice regardless.

---

## Concurrency

**PostgreSQL 9.5+** — `pop()` is atomic. Each worker locks one pending row with
`FOR UPDATE SKIP LOCKED` inside a transaction, so two workers never claim the same job:

```sql
SELECT * FROM jobs
  WHERE queue = :queue AND status = 'pending'
  ORDER BY id ASC
  LIMIT 1 FOR UPDATE SKIP LOCKED;
-- then UPDATE … SET status='processing' … in the same transaction
```

The driver is detected automatically from `PDO::ATTR_DRIVER_NAME`, so moving from SQLite in tests
to PostgreSQL in production needs no code change.

**SQLite and MySQL** fall back to the v0.2 shape (SELECT then UPDATE, no `FOR UPDATE` — SQLite
rejects the syntax). MySQL 8.0+ supports skip-locked but v0.3 does not enable it, for lack of test
coverage.

!!! warning "One worker per queue on SQLite and MySQL"
    Without skip-locked the claim step can race. SQLite's single-writer engine makes it rare, not
    impossible.

FIFO is best-effort: `SKIP LOCKED` does not re-evaluate `ORDER BY id` after taking the lock, so
under non-monotonic ids a lower id inserted concurrently can be claimed after a higher one. With
ordinary `SERIAL`/`AUTOINCREMENT` this is invisible.

### Caller-owned transactions

`pop()` opens a transaction only when `PDO::inTransaction()` is `false`. Called inside your own
transaction, the row lock is held by yours and released on your commit or rollback — `pop()` will
not commit or roll back an outer transaction.

## Related

- [karhu-db](db.md) — supplies the `Connection`
- [CLI](../cli.md) — where a worker entry point usually lives
