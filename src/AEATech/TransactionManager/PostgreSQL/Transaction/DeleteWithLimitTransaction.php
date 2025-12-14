<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\TransactionInterface;
use InvalidArgumentException;

/**
 * PostgreSQL-specific DELETE transaction with LIMIT support.
 *
 * Generates SQL:
 *   DELETE FROM "table" WHERE ctid IN (
 *       SELECT ctid
 *       FROM "table"
 *       WHERE "identifier_column" IN (?, ?, ...)
 *       LIMIT N
 *   )
 *
 * IMPORTANT:
 * - This is PostgreSQL-only due to use of the system column "ctid".
 * - Guarantees that at most N physical rows are deleted even if the
 *   identifier column is not unique.
 */
class DeleteWithLimitTransaction implements TransactionInterface
{
    public function __construct(
        private readonly PostgreSQLIdentifierQuoter $quoter,
        private readonly string $tableName,
        private readonly string $identifierColumn,
        private readonly mixed $identifierColumnType,
        private readonly array $identifiers,
        private readonly int $limit,
        private readonly bool $isIdempotent = true,
        private readonly StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ) {
        if ([] === $this->identifiers) {
            throw new InvalidArgumentException('Identifiers must not be empty.');
        }

        if (0 >= $this->limit) {
            throw new InvalidArgumentException('Limit must be a positive integer.');
        }
    }

    public function build(): Query
    {
        $identifiersCount = count($this->identifiers);

        $params = array_values($this->identifiers);
        $types = array_fill(0, $identifiersCount, $this->identifierColumnType);
        $placeholders = array_fill(0, $identifiersCount, '?');

        $quotedTable = $this->quoter->quoteIdentifier($this->tableName);
        $quotedColumn = $this->quoter->quoteIdentifier($this->identifierColumn);

        $sql = sprintf(
            'DELETE FROM %1$s WHERE ctid IN (' .
            'SELECT ctid FROM %1$s WHERE %2$s IN (%3$s) LIMIT %4$d' .
            ')',
            $quotedTable,
            $quotedColumn,
            implode(', ', $placeholders),
            $this->limit
        );

        return new Query($sql, $params, $types, $this->statementReusePolicy);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent ?? false;
    }
}
