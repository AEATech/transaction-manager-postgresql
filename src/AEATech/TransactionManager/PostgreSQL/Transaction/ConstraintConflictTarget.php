<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use InvalidArgumentException;

/**
 * ON CONFLICT ON CONSTRAINT some_unique_constraint
 */
final class ConstraintConflictTarget implements ConflictTargetInterface
{
    public function __construct(
        private readonly PostgreSQLIdentifierQuoter $quoter,
        private readonly string $constraintName,
    ) {
        if ('' === $this->constraintName) {
            throw new InvalidArgumentException('ConstraintConflictTarget requires non-empty $constraintName.');
        }
    }

    public function toSql(): string
    {
        return sprintf('ON CONFLICT ON CONSTRAINT %s', $this->quoter->quoteIdentifier($this->constraintName));
    }

    /**
     * For constraint-based conflicts we do not require that all key columns
     * are present in the INSERT column set, so validation is a no-op.
     *
     * @param string[] $availableColumns
     */
    public function validateAgainstColumns(array $availableColumns): void
    {
        // intentionally no-op
    }
}
