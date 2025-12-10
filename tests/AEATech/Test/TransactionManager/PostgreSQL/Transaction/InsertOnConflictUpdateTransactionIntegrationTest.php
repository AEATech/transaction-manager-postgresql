<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\Test\TransactionManager\PostgreSQL\IntegrationTestCase;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTarget;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertOnConflictUpdateTransaction;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(InsertOnConflictUpdateTransaction::class)]
class InsertOnConflictUpdateTransactionIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::db()->executeStatement(
            <<<'SQL'
CREATE TABLE tm_upsert_test (
    id INT PRIMARY KEY,
    email VARCHAR(128) NOT NULL UNIQUE,
    name VARCHAR(64) NOT NULL,
    age INT NOT NULL
)
SQL
        );

        // Seed an initial row that will be updated by the upsert
        self::db()->insert('tm_upsert_test', [
            'id' => 1,
            'email' => 'alex@example.com',
            'name' => 'Alex',
            'age' => 30,
        ], [
            'id' => ParameterType::INTEGER,
            'email' => ParameterType::STRING,
            'name' => ParameterType::STRING,
            'age' => ParameterType::INTEGER,
        ]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function upsertsRowsByPrimaryKeyConflict(): void
    {
        $rows = [
            // Existing PK=1 will be updated
            ['id' => 1, 'email' => 'alex@example.com', 'name' => 'Alexey', 'age' => 31],
            // New row will be inserted
            ['id' => 2, 'email' => 'bob@example.com',  'name' => 'Bob',    'age' => 25],
        ];

        $types = [
            'id' => ParameterType::INTEGER,
            'email' => ParameterType::STRING,
            'name' => ParameterType::STRING,
            'age' => ParameterType::INTEGER,
        ];

        $tx = new InsertOnConflictUpdateTransaction(
            new InsertValuesBuilder(),
            new PostgreSQLIdentifierQuoter(),
            'tm_upsert_test',
            $rows,
            ['name', 'age'],
            new ColumnsConflictTarget(new PostgreSQLIdentifierQuoter(), ['id']),
            $types,
        );

        $affected = $this->runTransaction($tx);

        // One INSERT + one UPDATE => 2 affected rows
        self::assertSame(2, $affected);

        $actual = self::db()
            ->executeQuery('SELECT id, email, name, age FROM tm_upsert_test ORDER BY id')
            ->fetchAllAssociative();

        $expected = [
            ['id' => 1, 'email' => 'alex@example.com', 'name' => 'Alexey', 'age' => 31],
            ['id' => 2, 'email' => 'bob@example.com',  'name' => 'Bob',    'age' => 25],
        ];

        self::assertSame($expected, $actual);
    }
}
