<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\StatementReusePolicy;

class DeleteWithLimitTransactionFactory
{
    public function __construct(
        private readonly PostgreSQLIdentifierQuoter $quoter
    ) {
    }

    /**
     * @param array<string|int, mixed> $identifiers Non-empty list of identifiers.
     */
    public function factory(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        int $limit,
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): DeleteWithLimitTransaction {
        return new DeleteWithLimitTransaction(
            $this->quoter,
            $tableName,
            $identifierColumn,
            $identifierColumnType,
            $identifiers,
            $limit,
            $isIdempotent,
            $statementReusePolicy
        );
    }
}
