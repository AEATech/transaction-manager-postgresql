<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;


use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use InvalidArgumentException;

/**
 * ON CONFLICT (col1, col2, ...)
 */
class ColumnsConflictTarget implements ConflictTargetInterface
{
    /**
     * @param PostgreSQLIdentifierQuoter $quoter
     * @param string[] $columns
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly PostgreSQLIdentifierQuoter $quoter,
        private readonly array $columns,
    ) {
        if ([] === $this->columns) {
            throw new InvalidArgumentException('ColumnsConflictTarget requires non-empty $columns.');
        }
    }

    public function toSql(): string
    {
        $quoted = $this->quoter->quoteIdentifiers($this->columns);

        return sprintf('ON CONFLICT (%s)', implode(', ', $quoted));
    }

    /**
     * @param string[] $availableColumns
     */
    public function validateAgainstColumns(array $availableColumns): void
    {
        $missing = array_diff($this->columns, $availableColumns);

        if ([] !== $missing) {
            throw new InvalidArgumentException(
                'Conflict columns must exist in rows. Missing: ' . implode(', ', $missing)
            );
        }
    }
}
