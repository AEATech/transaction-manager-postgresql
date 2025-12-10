<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;

class ConstraintConflictTargetFactory
{
    public function __construct(
        private readonly PostgreSQLIdentifierQuoter $quoter
    ) {
    }

    public function factory(string $constraintName): ConflictTargetInterface
    {
        return new ConstraintConflictTarget($this->quoter, $constraintName);
    }
}
