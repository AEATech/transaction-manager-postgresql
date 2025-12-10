<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use InvalidArgumentException;

/**
 * Represents PostgreSQL ON CONFLICT target.
 *
 * Implementations encapsulate how to build:
 *   - ON CONFLICT (col1, col2, ...)
 *   - ON CONFLICT ON CONSTRAINT some_constraint
 */
interface ConflictTargetInterface
{
    /**
     * Builds the SQL fragment for the ON CONFLICT target, e.g.:
     *   - 'ON CONFLICT ("id")'
     *   - 'ON CONFLICT ON CONSTRAINT "my_table_pkey"'
     */
    public function toSql(): string;

    /**
     * Optional validation against the list of insertable columns.
     *
     * @param string[] $availableColumns
     *
     * @throws InvalidArgumentException When configuration is inconsistent with the insert columns.
     */
    public function validateAgainstColumns(array $availableColumns): void;
}