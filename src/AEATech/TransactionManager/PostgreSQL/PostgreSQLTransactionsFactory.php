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

class PostgreSQLTransactionsFactory implements PostgreSQLTransactionsFactoryInterface
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

    public function createSql(
        string $sql,
        array $params = [],
        array $types = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return new SqlTransaction($sql, $params, $types, $isIdempotent, $statementReusePolicy);
    }

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

    public function conflictTargetByColumns(array $columns): ConflictTargetInterface
    {
        return $this->columnsConflictTargetFactory->factory($columns);
    }

    public function conflictTargetByConstraint(string $constraintName): ConflictTargetInterface
    {
        return $this->constraintConflictTargetFactory->factory($constraintName);
    }
}
