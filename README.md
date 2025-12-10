# AEATech Transaction Manager – PostgreSQL

Lightweight module for generating safe and efficient PostgreSQL statements:
- INSERT
- INSERT ... ON CONFLICT DO NOTHING (aka INSERT IGNORE)
- INSERT ... ON CONFLICT ... DO UPDATE (UPSERT)
- DELETE, DELETE with LIMIT (via `ctid`)
- UPDATE, UPDATE with CASE WHEN ... THEN ...

This package is an extension of `aeatech/transaction-manager-core`.
It only builds SQL and parameters; the core package handles execution, retries, and transaction boundaries.
For Doctrine DBAL users, there is an adapter package: `aeatech/transaction-manager-doctrine-adapter`.

System requirements:
- PHP >= 8.2
- ext-pdo
- PostgreSQL 12+ (tested with 16; `ctid`-based DELETE LIMIT is PostgreSQL-specific)

Installation (Composer):
```bash
composer require aeatech/transaction-manager-postgresql
```

## Quick start

```php
<?php
use AEATech\TransactionManager\DoctrineAdapter\DbalConnectionAdapter;
use AEATech\TransactionManager\ExecutionPlanBuilder;
use AEATech\TransactionManager\ExponentialBackoff;
use AEATech\TransactionManager\GenericErrorClassifier;
use AEATech\TransactionManager\IsolationLevel;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLErrorHeuristics;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\TransactionsFactory as PgTxFactory;
use AEATech\TransactionManager\RetryPolicy;
use AEATech\TransactionManager\SystemSleeper;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\Transaction\Internal\UpdateWhenThenDefinitionsBuilder;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertOnConflictUpdateTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\DeleteWithLimitTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTargetFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConstraintConflictTargetFactory;
use AEATech\TransactionManager\Transaction\DeleteTransactionFactory;
use AEATech\TransactionManager\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransactionFactory;
use AEATech\TransactionManager\TransactionManager;
use AEATech\TransactionManager\TxOptions;

// 1) Create a connection adapter (Doctrine DBAL example):
// $dbal = new Doctrine\DBAL\Connection(...);
$conn = new DbalConnectionAdapter($dbal);

// 2) Configure the TransactionManager from the core:
$errorClassifier = new GenericErrorClassifier(new PostgreSQLErrorHeuristics());
$tm = new TransactionManager(
    executionPlanBuilder: new ExecutionPlanBuilder(),
    connection: $conn,
    errorClassifier: $errorClassifier,
    sleeper: new SystemSleeper(),
);

// 3) Create the PostgreSQL transactions factory
$quoter = new PostgreSQLIdentifierQuoter();
$insertValuesBuilder = new InsertValuesBuilder();
$updateWhenThenDefs = new UpdateWhenThenDefinitionsBuilder();

$txFactory = new PgTxFactory(
    insertTransactionFactory: new InsertTransactionFactory(
        $insertValuesBuilder,
        $quoter,
    ),
    insertIgnoreTransactionFactory: new InsertIgnoreTransactionFactory(
        $insertValuesBuilder,
        $quoter,
    ),
    insertOnConflictUpdateTransactionFactory: new InsertOnConflictUpdateTransactionFactory(
        $insertValuesBuilder,
        $quoter,
    ),
    deleteTransactionFactory: new DeleteTransactionFactory($quoter),
    deleteWithLimitTransactionFactory: new DeleteWithLimitTransactionFactory($quoter),
    updateTransactionFactory: new UpdateTransactionFactory($quoter),
    updateWhenThenTransactionFactory: new UpdateWhenThenTransactionFactory($updateWhenThenDefs, $quoter),
    columnsConflictTargetFactory: new ColumnsConflictTargetFactory($quoter),
    constraintConflictTargetFactory: new ConstraintConflictTargetFactory($quoter),
);

// 4) Example: UPSERT by unique email
$tx = $txFactory->createInsertOnConflictUpdate(
    tableName: 'users',
    rows: [
        ['id' => 1, 'email' => 'foo@example.com', 'name' => 'Foo'],
    ],
    updateColumns: ['name'],
    conflictTarget: $txFactory->conflictTargetByColumns(['email']),
    columnTypes: [
        'id' => \PDO::PARAM_INT,
    ],
    isIdempotent: true,
);

$options = new TxOptions(
    isolationLevel: IsolationLevel::ReadCommitted,
    retryPolicy: new RetryPolicy(3, new ExponentialBackoff())
);

$runResult = $tm->run($tx, $options);
```

## Usage examples

### 1) INSERT
```php
$tx = $txFactory->createInsert(
    tableName: 'audit_log',
    rows: [
        ['id' => 1, 'event' => 'login', 'meta' => json_encode(['ip' => '1.1.1.1'])],
        ['id' => 2, 'event' => 'logout', 'meta' => null],
    ],
    columnTypes: [
        'id' => \PDO::PARAM_INT,
        'event' => \PDO::PARAM_STR,
        // 'meta' type can be omitted; DBAL will infer
    ],
    isIdempotent: false,
);
$tm->run($tx, $options);
```

### 2) INSERT IGNORE (ON CONFLICT DO NOTHING)
```php
$tx = $txFactory->createInsertIgnore(
    tableName: 'users',
    rows: [
        ['id' => 1, 'email' => 'a@example.com'],
        ['id' => 1, 'email' => 'a@example.com'], // duplicate id — ignored
    ],
    // columnTypes optional
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 3) UPSERT (ON CONFLICT DO UPDATE) by columns
```php
$target = $txFactory->conflictTargetByColumns(['email']);

$tx = $txFactory->createInsertOnConflictUpdate(
    tableName: 'users',
    rows: [
        ['id' => 10, 'email' => 'x@example.com', 'name' => 'Alice'],
        ['id' => 11, 'email' => 'y@example.com', 'name' => 'Bob'],
    ],
    updateColumns: ['name'],
    conflictTarget: $target,
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 4) UPSERT (ON CONFLICT DO UPDATE) by constraint name
```php
$target = $txFactory->conflictTargetByConstraint('uniq_users_email');

$tx = $txFactory->createInsertOnConflictUpdate(
    tableName: 'users',
    rows: [
        ['id' => 10, 'email' => 'x@example.com', 'name' => 'Alice'],
    ],
    updateColumns: ['name'],
    conflictTarget: $target,
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 5) DELETE by identifiers
```php
$tx = $txFactory->createDelete(
    tableName: 'users',
    identifierColumn: 'id',
    identifierColumnType: \PDO::PARAM_INT,
    identifiers: [1, 2, 3],
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 6) DELETE with LIMIT (PostgreSQL only, via ctid)
```php
$tx = $txFactory->createDeleteWithLimit(
    tableName: 'events',
    identifierColumn: 'account_id',
    identifierColumnType: \PDO::PARAM_INT,
    identifiers: [42],        // delete rows for account_id=42
    limit: 1000,              // at most 1000 physical rows
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 7) UPDATE by identifiers
```php
$tx = $txFactory->createUpdate(
    tableName: 'users',
    rows: [
        ['id' => 1, 'name' => 'Renamed'],
        ['id' => 2, 'name' => 'Also Renamed'],
    ],
    identifierColumn: 'id',
    identifierColumnType: \PDO::PARAM_INT,
    updateColumns: ['name'],
    updateColumnTypes: [
        'name' => \PDO::PARAM_STR,
    ],
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 8) UPDATE WHEN ... THEN ...
```php
$tx = $txFactory->createUpdateWhenThen(
    tableName: 'users',
    rows: [
        ['id' => 1, 'quota' => 100, 'plan' => 'basic'],
        ['id' => 2, 'quota' => 250, 'plan' => 'pro'],
    ],
    identifierColumn: 'id',
    identifierColumnType: \PDO::PARAM_INT,
    updateColumns: ['quota', 'plan'],
    updateColumnTypes: [
        'quota' => \PDO::PARAM_INT,
        'plan'  => \PDO::PARAM_STR,
    ],
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 9) Raw SQL
```php
$sql = 'UPDATE "users" SET "name" = ? WHERE "id" = ?';
$params = ['John', 123];
$types = [\PDO::PARAM_STR, \PDO::PARAM_INT];
$tx = $txFactory->createSql($sql, $params, $types, isIdempotent: true);
$tm->run($tx, $options);
```

## Parameters and types
- rows: array of homogeneous associative arrays like `['column' => value, ...]`. All rows must have the same set of keys (columns).
- columnTypes: `array<string, int|string>` — mapping `column => parameter type` (PDO::PARAM_*, `Doctrine\DBAL\ParameterType::*` or string type names supported by DBAL). Optional — DBAL will try to infer types.
- isIdempotent: a flag for the transaction manager indicating retry safety. Semantics depend on your retry policy:
  - false (default): a re-run may change the outcome (e.g., plain INSERT).
  - true: the statement is designed to be idempotent (e.g., UPSERT or DO NOTHING), allowing the manager to apply more aggressive retries.

Additional notes for delete/update:
- identifierColumn / identifiers:
  - Use a primary key or another unique column to avoid unintended data changes.
  - Provide a non-empty array of scalar identifiers.
- DELETE with LIMIT:
  - Implemented via `ctid` selection inside a subquery. Guarantees that at most N physical rows are deleted, even if the identifier column is not unique.
  - `limit` must be a positive integer; not all provided identifiers may be deleted in one run.
- UPDATE by identifiers:
  - `updateColumnTypes` apply to the columns in `SET` clause; the identifier type is provided separately via `identifierColumnType`.
- UPDATE WHEN ... THEN:
  - `rows` must include the identifier column and all columns listed in `updateColumns`.

## PostgreSQL specifics and nuances
- ON CONFLICT targets:
  - `conflictTargetByColumns([col1, col2, ...])` generates `ON CONFLICT (col1, col2, ...)` — all listed columns must be present in each inserted row; otherwise an `InvalidArgumentException` will be thrown during validation.
  - `conflictTargetByConstraint('constraint_name')` generates `ON CONFLICT ON CONSTRAINT "constraint_name"` — column presence is not validated by the builder, but the constraint must exist in the DB.
- Identifier quoting:
  - Pass names without quotes — the library uses `PostgreSQLIdentifierQuoter` to quote identifiers with double quotes.
- Idempotency hints:
  - `createInsertIgnore` and `createInsertOnConflictUpdate` are typically idempotent — pass `isIdempotent: true` if your business logic agrees.
  - Plain `createInsert` is usually non-idempotent.
- Isolation level:
  - Choose appropriate `TxOptions::isolationLevel`. For UPSERT-heavy workloads, `ReadCommitted` is common, but `Serializable` can be paired with retries for stricter guarantees.
- Error classification & retries:
  - Use `GenericErrorClassifier(new PostgreSQLErrorHeuristics())` with the core TransactionManager. It classifies transient issues (e.g., `40001` serialization failure, `40P01` deadlock, `55P03` lock not available) and connection losses for safe retries according to your `RetryPolicy`.
- Large batches:
  - Insert in batches (e.g., 100–1000 rows) to avoid driver limits and huge statements.
- JSON, arrays, custom types:
  - For complex types, rely on your DBAL mapping and pass explicit `columnTypes` when needed.
- DELETE with LIMIT (ctid) caveats:
  - The physical row order is not guaranteed. If you need deterministic ordering, perform chunking at the application level with stable predicates and small limits.

## How it works
- SQL and parameters are built inside transaction objects (`InsertTransaction`, `InsertIgnoreTransaction`, `InsertOnConflictUpdateTransaction`, etc.).
- Identifiers (table and column names) are quoted safely for PostgreSQL.
- The result is an `AEATech\TransactionManager\Query` (from the core), which is executed via the provided connection adapter.

## Running tests

### 1) Via Docker Compose (recommended for reproducibility)

Bring up services for your target PHP/PostgreSQL versions and run PHPUnit inside the PHP CLI containers.

Start services (PHP 8.2/8.3/8.4 with PostgreSQL 16, 17, 18 see `docker/docker-compose.yml` for details):

```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml up -d --build
```

Install dependencies inside the PHP container (example for PHP 8.3):

```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.3-pg16 composer install
```

Run tests for PHP 8.2 and PostgreSQL 16:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.2-pg16 vendor/bin/phpunit
```

For PHP 8.3 and PostgreSQL 16:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.3-pg16 vendor/bin/phpunit
```

For PHP 8.4 and PostgreSQL 16:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.4-pg16 vendor/bin/phpunit
```

Run tests for PHP 8.2 and PostgreSQL 17:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.2-pg17 vendor/bin/phpunit
```

For PHP 8.3 and PostgreSQL 17:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.3-pg17 vendor/bin/phpunit
```

For PHP 8.4 and PostgreSQL 17:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.4-pg17 vendor/bin/phpunit
```

Run tests for PHP 8.2 and PostgreSQL 18:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.2-pg18 vendor/bin/phpunit
```

For PHP 8.3 and PostgreSQL 17:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.3-pg18 vendor/bin/phpunit
```

For PHP 8.4 and PostgreSQL 17:
```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T php-cli-8.4-pg18 vendor/bin/phpunit
```

Run all configured variants:

```bash
for v in php-cli-8.2-pg16 php-cli-8.2-pg17 php-cli-8.2-pg18 php-cli-8.3-pg16 php-cli-8.3-pg17 php-cli-8.3-pg18 php-cli-8.4-pg16 php-cli-8.4-pg17 php-cli-8.4-pg18 ; do \
  echo "Testing PHP $v..."; \
  docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml exec -T $v vendor/bin/phpunit || break; \
done
```

To stop and remove containers:

```bash
docker-compose -p aeatech-transaction-manager-postgresql -f docker/docker-compose.yml down -v
```

## License

MIT License. See [LICENSE](./LICENSE) for details.

