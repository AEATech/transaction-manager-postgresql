<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\Test\TransactionManager\PostgreSQL\IntegrationTestCase;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\Transaction\DeleteTransaction;
use AEATech\TransactionManager\Transaction\InsertTransaction;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(DeleteTransaction::class)]
class DeleteTransactionIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::db()->executeStatement(
            <<<'SQL'
CREATE TABLE tm_delete_test (
    id INT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    age INT NOT NULL
)
SQL
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function deleteSuccessfully(): void
    {
        // Init state
        $initState = [
            ['id' => 1, 'name' => 'Alex', 'age' => 30],
            ['id' => 2, 'name' => 'Bob',  'age' => 25],
            ['id' => 3, 'name' => 'John', 'age' => 40],
            ['id' => 4, 'name' => 'Mary', 'age' => 28],
        ];

        $quoter = new PostgreSQLIdentifierQuoter();

        $initTransaction = new InsertTransaction(
            new InsertValuesBuilder(),
            $quoter,
            'tm_delete_test',
            $initState
        );

        $affectedRows = $this->runTransaction($initTransaction);
        self::assertSame(count($initState), $affectedRows);

        // Prepare identifiers to delete every other row
        $identifiersForDelete = [];
        $expected = [];
        foreach ($initState as $index => $item) {
            if ($index % 2 === 0) {
                $identifiersForDelete[] = $item['id'];
            } else {
                $expected[] = $item;
            }
        }

        $deleteTransaction = new DeleteTransaction(
            $quoter,
            'tm_delete_test',
            'id',
            ParameterType::INTEGER,
            $identifiersForDelete
        );

        $affectedRows = $this->runTransaction($deleteTransaction);
        self::assertSame(count($identifiersForDelete), $affectedRows);

        $actual = self::db()
            ->executeQuery('SELECT id, name, age FROM tm_delete_test ORDER BY id')
            ->fetchAllAssociative();

        self::assertSame($expected, $actual);
    }
}
