<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\TransactionInterface;
use InvalidArgumentException;

/**
 * PostgreSQL upsert by PK/UNIQUE constraint or conflict columns.
 *
 * Generates SQL:
 *
 *   INSERT INTO table (cols...)
 *   VALUES ...
 *   <conflictTargetSql>       -- either ON CONFLICT (cols...) or ON CONFLICT ON CONSTRAINT ...
 *   DO UPDATE SET col = EXCLUDED.col, ...
 */
class InsertOnConflictUpdateTransaction implements TransactionInterface
{
    /**
     * @param InsertValuesBuilder                $insertValuesBuilder
     * @param PostgreSQLIdentifierQuoter         $quoter
     * @param string                             $tableName
     * @param array<array<string, mixed>>        $rows
     * @param string[]                           $updateColumns Columns that will be updated on conflict
     * @param ConflictTargetInterface            $conflictTarget Conflict target (columns or constraint)
     * @param array<string, int|string>          $columnTypes
     * @param bool                               $isIdempotent
     */
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
        private readonly PostgreSQLIdentifierQuoter $quoter,
        private readonly string $tableName,
        private readonly array $rows,
        private readonly array $updateColumns,
        private readonly ConflictTargetInterface $conflictTarget,
        private readonly array $columnTypes = [],
        private readonly bool $isIdempotent = false,
    ) {
        if ([] === $this->updateColumns) {
            throw new InvalidArgumentException(
                'InsertOnConflictUpdateTransaction requires non-empty $updateColumns.'
            );
        }
    }

    public function build(): Query
    {
        [$valuesSql, $params, $types, $columns] =
            $this->insertValuesBuilder->build($this->rows, $this->columnTypes);

        // Ensure that all update columns are part of the inserted data
        $missingUpdate = array_diff($this->updateColumns, $columns);

        if ([] !== $missingUpdate) {
            throw new InvalidArgumentException(
                'Update columns must exist in rows. Missing: ' . implode(', ', $missingUpdate)
            );
        }

        // Let the conflict target validate itself against the available columns
        $this->conflictTarget->validateAgainstColumns($columns);

        $quotedColumns = $this->quoter->quoteIdentifiers($columns);

        // Build conflict target SQL fragment
        $conflictTargetSql = $this->conflictTarget->toSql();

        // DO UPDATE SET col1 = EXCLUDED.col1, col2 = EXCLUDED.col2, ...
        $updateAssignments = [];

        foreach ($this->updateColumns as $column) {
            $updateAssignments[] = sprintf('%1$s = EXCLUDED.%1$s', $this->quoter->quoteIdentifier($column));
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s %s DO UPDATE SET %s',
            $this->quoter->quoteIdentifier($this->tableName),
            implode(', ', $quotedColumns),
            $valuesSql,
            $conflictTargetSql,
            implode(', ', $updateAssignments),
        );

        return new Query($sql, $params, $types);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
