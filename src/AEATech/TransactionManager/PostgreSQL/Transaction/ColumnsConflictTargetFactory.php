<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;

class ColumnsConflictTargetFactory
{
    public function __construct(
        private readonly PostgreSQLIdentifierQuoter $quoter
    ) {
    }

    /**
     * @param string[] $columns
     */
    public function factory(array $columns): ConflictTargetInterface
    {
        return new ColumnsConflictTarget($this->quoter, $columns);
    }
}
