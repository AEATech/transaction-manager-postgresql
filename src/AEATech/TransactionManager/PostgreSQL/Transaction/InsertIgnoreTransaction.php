<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\TransactionInterface;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;

class InsertIgnoreTransaction implements TransactionInterface
{
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
        private readonly PostgreSQLIdentifierQuoter $quoter,
        private readonly string $tableName,
        private readonly array $rows,
        private readonly array $columnTypes = [],
        private readonly bool $isIdempotent = false,
    ) {
    }

    public function build(): Query
    {
        [$valuesSql, $params, $types, $columns] =
            $this->insertValuesBuilder->build($this->rows, $this->columnTypes);

        $quotedColumns = $this->quoter->quoteIdentifiers($columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON CONFLICT DO NOTHING',
            $this->quoter->quoteIdentifier($this->tableName),
            implode(', ', $quotedColumns),
            $valuesSql,
        );

        return new Query($sql, $params, $types);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
