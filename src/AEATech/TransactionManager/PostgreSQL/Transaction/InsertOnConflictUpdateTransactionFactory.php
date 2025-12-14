<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;

class InsertOnConflictUpdateTransactionFactory
{
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
        private readonly PostgreSQLIdentifierQuoter $quoter,
    ) {
    }

    public function factory(
        string $tableName,
        array $rows,
        array $updateColumns,
        ConflictTargetInterface $conflictTarget,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): InsertOnConflictUpdateTransaction {
        return new InsertOnConflictUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            $tableName,
            $rows,
            $updateColumns,
            $conflictTarget,
            $columnTypes,
            $isIdempotent,
            $statementReusePolicy
        );
    }
}
