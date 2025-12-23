<?php
declare(strict_types=1);


namespace AEATech\TransactionManager\PostgreSQL;

use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTargetFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConflictTargetInterface;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConstraintConflictTargetFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\DeleteWithLimitTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertOnConflictUpdateTransactionFactory;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\DeleteTransactionFactory;
use AEATech\TransactionManager\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\Transaction\SqlTransaction;
use AEATech\TransactionManager\Transaction\UpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransactionFactory;
use AEATech\TransactionManager\TransactionInterface;
use InvalidArgumentException;

/**
 * Convenience facade for creating PostgreSQL-specific TransactionInterface instances.
 *
 * This class exposes a compact, high-level API for building common write
 * transactions (INSERT, DELETE, UPDATE, CASE-based UPDATE) as well as
 * PostgreSQL upserts via `ON CONFLICT ... DO UPDATE`.
 *
 * Typical usage:
 *
 *     $transactions = new TransactionsFactory(
 *         insertTransactionFactory: $insertFactory,
 *         insertIgnoreTransactionFactory: $insertIgnoreFactory,
 *         insertOnConflictUpdateTransactionFactory: $upsertFactory,
 *         deleteTransactionFactory: $deleteFactory,
 *         deleteWithLimitTransactionFactory: $deleteWithLimitFactory,
 *         updateTransactionFactory: $updateFactory,
 *         updateWhenThenTransactionFactory: $updateWhenThenFactory,
 *         columnsConflictTargetFactory: $columnsTargetFactory,
 *         constraintConflictTargetFactory: $constraintTargetFactory
 *     );
 *
 *     $tx = $transactions->createInsertOnConflictUpdate(
 *         tableName: 'users',
 *         rows: [
 *             ['id' => 1, 'email' => 'foo@example.com', 'name' => 'Foo'],
 *         ],
 *         updateColumns: ['name'],
 *         conflictTarget: $transactions->conflictTargetByColumns(['email'])
 *     );
 *
 *     $runResult = $transactionManager->run($tx, $options);
 */
class TransactionsFactory
{
    public function __construct(
        private readonly InsertTransactionFactory $insertTransactionFactory,
        private readonly InsertIgnoreTransactionFactory $insertIgnoreTransactionFactory,
        private readonly InsertOnConflictUpdateTransactionFactory $insertOnConflictUpdateTransactionFactory,
        private readonly DeleteTransactionFactory $deleteTransactionFactory,
        private readonly DeleteWithLimitTransactionFactory $deleteWithLimitTransactionFactory,
        private readonly UpdateTransactionFactory $updateTransactionFactory,
        private readonly UpdateWhenThenTransactionFactory $updateWhenThenTransactionFactory,
        private readonly ColumnsConflictTargetFactory $columnsConflictTargetFactory,
        private readonly ConstraintConflictTargetFactory $constraintConflictTargetFactory,
    ) {
    }

    /**
     * Creates an INSERT transaction:
     *
     *     INSERT INTO tableName (col1, col2, ...)
     *     VALUES (...), (...), ...
     *
     * @param string $tableName
     *   Logical table name without quoting. The underlying implementation will
     *   quote the identifier as needed for PostgreSQL. The name should:
     *   - be non-empty;
     *   - correspond to an existing table in the current schema;
     *   - be provided without quotes.
     *
     * @param array<array<string, mixed>> $rows
     *   Non-empty list of rows to insert. Each row is an associative array of
     *   column => value pairs. All rows are expected to share the same set of
     *   keys (column names). Keys are unquoted; quoting is handled internally.
     *
     * @param array<string, int|string> $columnTypes
     *   Optional mapping of column name => parameter type for your DBAL
     *   (PDO::PARAM_* or Doctrine DBAL ParameterType constants). Keys must
     *   match the column names used in $rows.
     *
     * @param bool $isIdempotent
     *   Whether this transaction can be safely retried by the Transaction
     *   Manager in case of transient failures. Plain INSERT is usually
     *   considered non-idempotent (default: false).
     *
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same
     *   statement object for multiple executions.
     *
     * Validation and errors:
     *   This method itself does not validate shapes and does not throw.
     *   Inconsistent input (e.g., empty $rows, mismatched keys) may cause an
     *   InvalidArgumentException later when the transaction is built/executed.
     *
     * @return TransactionInterface
     */
    public function createInsert(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->insertTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy,
        );
    }

    /**
     * Creates an INSERT IGNORE transaction for PostgreSQL.
     *
     * Conceptually similar to MySQL's `INSERT IGNORE`, this variant attempts to
     * insert all rows while ignoring constraint violations when possible
     * (implementation is PostgreSQL-specific inside the underlying transaction).
     *
     * Parameters follow the same conventions as {@see createInsert()}.
     *
     * Idempotency: despite ignoring conflicts, the statement is typically
     * treated as non-idempotent by default (set to true only if your business
     * logic explicitly requires it and your retry policy is aligned).
     *
     * @param string $tableName
     * @param array<array<string, mixed>> $rows
     * @param array<string, int|string> $columnTypes
     * @param bool $isIdempotent
     * @param StatementReusePolicy $statementReusePolicy
     *
     * @return TransactionInterface
     */
    public function createInsertIgnore(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->insertIgnoreTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy,
        );
    }

    /**
     * Creates a PostgreSQL UPSERT (INSERT ... ON CONFLICT DO UPDATE) transaction.
     *
     * Generates SQL of the form:
     *
     *     INSERT INTO tableName (col1, col2, ...)
     *     VALUES (...), (...), ...
     *     ON CONFLICT <conflictTarget>
     *     DO UPDATE SET colX = EXCLUDED.colX, colY = EXCLUDED.colY, ...
     *
     * @param string $tableName
     *   Target table name (unquoted; quoting handled internally).
     *
     * @param array<array<string, mixed>> $rows
     *   Non-empty list of rows. Each row is an associative array of column
     *   values. All keys should be consistent across rows.
     *
     * @param string[] $updateColumns
     *   Columns to be updated when a conflict is detected. Each column must be
     *   present in every row in $rows, otherwise an InvalidArgumentException is
     *   thrown during build.
     *
     * @param ConflictTargetInterface $conflictTarget
     *   Conflict target descriptor, built with
     *   {@see conflictTargetByColumns()} or {@see conflictTargetByConstraint()}.
     *   It is validated against available columns during build.
     *
     * @param array<string, int|string> $columnTypes
     *   Optional per-column DBAL parameter types.
     *
     * @param bool $isIdempotent
     *   Upserts are often close to idempotent (repeating converges to the same
     *   final state), but triggers/side effects can break strict idempotency.
     *   Set to true only after reviewing your semantics.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same
     *   statement object for multiple executions.
     *
     * @return TransactionInterface
     */
    public function createInsertOnConflictUpdate(
        string $tableName,
        array $rows,
        array $updateColumns,
        ConflictTargetInterface $conflictTarget,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->insertOnConflictUpdateTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            updateColumns: $updateColumns,
            conflictTarget: $conflictTarget,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy,
        );
    }

    /**
     * Wraps an arbitrary SQL statement in a transaction object.
     *
     * This is an escape hatch for advanced scenarios where a dedicated
     * transaction type is unavailable. The SQL is not validated or sanitized by
     * the library and is executed as-is by the underlying driver/DBAL.
     *
     * WARNING: Make sure the SQL is correct for PostgreSQL, safe for retries
     * given your $isIdempotent choice, and free from injection vulnerabilities.
     *
     * @param string $sql
     *    Arbitrary SQL (typically a single DML statement).
     *
     * @param array<int|string, mixed> $params
     *    Positional or named parameters.
     *
     * @param array<int|string, int|string> $types
     *    Optional parameter types.
     *
     * @param bool $isIdempotent
     *    Whether retries are safe for this SQL.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same statement object for multiple executions.
     *
     * @return TransactionInterface
     */
    public function createSql(
        string $sql,
        array $params = [],
        array $types = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return new SqlTransaction($sql, $params, $types, $isIdempotent, $statementReusePolicy);
    }

    /**
     * Creates a DELETE transaction by identifier column:
     *
     *     DELETE FROM tableName
     *     WHERE identifierColumn IN (?, ?, ...)
     *
     * Suitable for removing a known finite set of rows by their identifiers
     * (typically a primary key or other unique column).
     *
     * @param string $tableName
     *    Unquoted table name (quoted internally).
     *
     * @param string $identifierColumn
     *    Column name used in the WHERE ... IN (...).
     *
     * @param mixed $identifierColumnType
     *    DBAL type or PDO::PARAM_* for IDs.
     *
     * @param array<int, scalar> $identifiers
     *    Non-empty list of IDs.
     *
     * @param bool $isIdempotent
     *    Generally safe to treat as idempotent (default true), but review business-level side effects.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *    Controls whether the underlying query builder should reuse the same statement object for multiple executions.
     *
     * @return TransactionInterface
     */
    public function createDelete(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->deleteTransactionFactory->factory(
            tableName: $tableName,
            identifierColumn: $identifierColumn,
            identifierColumnType: $identifierColumnType,
            identifiers: $identifiers,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy,
        );
    }

    /**
     * Creates a PostgreSQL-specific DELETE transaction with a LIMIT-like cap.
     *
     * Limits the maximum number of rows deleted in a single execution. The
     * concrete SQL shape is implemented in the PostgreSQL transaction layer.
     *
     * @param string $tableName
     * @param string $identifierColumn
     * @param mixed $identifierColumnType
     * @param array<int, scalar> $identifiers Non-empty list of identifiers.
     * @param int $limit Positive integer; must be > 0.
     * @param bool $isIdempotent See notes below.
     * @param StatementReusePolicy $statementReusePolicy
     *
     * Idempotency notes:
     * - If $limit >= count($identifiers), behavior resembles {@see createDelete()} and
     *   is usually idempotent at the database level.
     * - If $limit < count($identifiers), retries may delete additional rows, and
     *   you should consider the operation non-idempotent.
     *
     * @return TransactionInterface
     *
     * @throws InvalidArgumentException If $limit <= 0.
     */
    public function createDeleteWithLimit(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        int $limit,
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->deleteWithLimitTransactionFactory->factory(
            tableName: $tableName,
            identifierColumn: $identifierColumn,
            identifierColumnType: $identifierColumnType,
            identifiers: $identifiers,
            limit: $limit,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy,
        );
    }

    /**
     * Creates a bulk UPDATE transaction that assigns the same values to all
     * rows selected by a list of identifiers:
     *
     *     UPDATE tableName
     *     SET col1 = ?, col2 = ?, ...
     *     WHERE identifierColumn IN (?, ?, ...)
     *
     * @param string $tableName
     *    Target table (unquoted name).
     *
     * @param string $identifierColumn
     *    Column used in WHERE ... IN (...).
     *
     * @param mixed $identifierColumnType
     *    Type for identifier parameters.
     *
     * @param array<int, scalar> $identifiers
     *    Non-empty list of identifiers.
     *
     * @param array<string, mixed> $columnsWithValuesForUpdate
     *    Column => new value map.
     *
     * @param array<string, int|string> $columnTypes
     *    Optional per-column parameter types.
     *
     * @param bool $isIdempotent
     *    Whether repeating is safe for your logic (default true).
     *
     * @param StatementReusePolicy $statementReusePolicy
     *    Controls whether the underlying query builder should reuse the same statement object for multiple executions.
     *
     * @return TransactionInterface
     */
    public function createUpdate(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        array $columnsWithValuesForUpdate,
        array $columnTypes = [],
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->updateTransactionFactory->factory(
            tableName: $tableName,
            identifierColumn: $identifierColumn,
            identifierColumnType: $identifierColumnType,
            identifiers: $identifiers,
            columnsWithValuesForUpdate: $columnsWithValuesForUpdate,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy,
        );
    }

    /**
     * Creates a CASE-based bulk UPDATE that assigns different values to
     * different rows within a single SQL statement.
     *
     * Generates SQL of the form:
     *
     *     UPDATE tableName
     *     SET
     *       col1 = CASE
     *         WHEN identifierColumn = ? THEN ?
     *         WHEN identifierColumn = ? THEN ?
     *         ...
     *         ELSE col1
     *       END,
     *       ...
     *     WHERE identifierColumn IN (?, ?, ...)
     *
     * @param string $tableName
     *    Unquoted table name.
     *
     * @param array<int, array<string, mixed>> $rows
     *    Each element must contain:
     *        [$identifierColumn => <id>, <updateColumn1> => <value>, ...]
     *
     * @param string $identifierColumn
     *    Discriminator column used in CASE and WHERE.
     *
     * @param mixed $identifierColumnType
     *    DBAL/PDO type for identifier params.
     *
     * @param string[] $updateColumns
     *    List of columns to update via CASE.
     *
     * @param array<string, int|string|null> $updateColumnTypes
     *    Optional per-column types.
     *
     * @param bool $isIdempotent
     *    Whether repeating the same update is safe (default true).
     *
     * @param StatementReusePolicy $statementReusePolicy
     *    Controls whether the underlying query builder should reuse the same statement object for multiple executions.
     *
     * @return TransactionInterface
     */
    public function createUpdateWhenThen(
        string $tableName,
        array $rows,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $updateColumns,
        array $updateColumnTypes = [],
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->updateWhenThenTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            identifierColumn: $identifierColumn,
            identifierColumnType: $identifierColumnType,
            updateColumns: $updateColumns,
            updateColumnTypes: $updateColumnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy,
        );
    }

    /**
     * Helper to build `ON CONFLICT (col1, col2, ...)` conflict target.
     *
     * Use this when your upsert should match conflicts by one or more columns.
     * The provided column names must exist in the inserted rows used by
     * {@see createInsertOnConflictUpdate()}.
     *
     * @param string[] $columns Non-empty list of column names.
     * @return ConflictTargetInterface
     */
    public function conflictTargetByColumns(array $columns): ConflictTargetInterface
    {
        return $this->columnsConflictTargetFactory->factory($columns);
    }

    /**
     * Helper to build `ON CONFLICT ON CONSTRAINT <name>` conflict target.
     *
     * Use this when your upsert should match conflicts by a specific UNIQUE or
     * PRIMARY KEY constraint name.
     *
     * @param string $constraintName Existing constraint name in the target table.
     * @return ConflictTargetInterface
     */
    public function conflictTargetByConstraint(string $constraintName): ConflictTargetInterface
    {
        return $this->constraintConflictTargetFactory->factory($constraintName);
    }
}
