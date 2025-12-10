<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;

class DeleteWithLimitTransactionFactory
{
    public function __construct(
        private readonly PostgreSQLIdentifierQuoter $quoter
    ) {
    }

    public function factory(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        int $limit,
        bool $isIdempotent = true,
    ): DeleteWithLimitTransaction {
        return new DeleteWithLimitTransaction(
            $this->quoter,
            $tableName,
            $identifierColumn,
            $identifierColumnType,
            $identifiers,
            $limit,
            $isIdempotent
        );
    }
}
