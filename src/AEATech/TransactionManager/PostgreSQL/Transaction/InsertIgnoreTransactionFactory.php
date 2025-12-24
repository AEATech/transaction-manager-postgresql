<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;

class InsertIgnoreTransactionFactory
{
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
        private readonly PostgreSQLIdentifierQuoter $quoter,
    ) {
    }

    /**
     * @param array<array<string, mixed>> $rows
     * @param array<string, mixed> $columnTypes
     */
    public function factory(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): InsertIgnoreTransaction {
        return new InsertIgnoreTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            $tableName,
            $rows,
            $columnTypes,
            $isIdempotent,
            $statementReusePolicy
        );
    }
}
