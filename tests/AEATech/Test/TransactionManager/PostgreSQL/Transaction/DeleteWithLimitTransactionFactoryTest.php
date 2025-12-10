<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\Transaction\DeleteWithLimitTransaction;
use AEATech\TransactionManager\PostgreSQL\Transaction\DeleteWithLimitTransactionFactory;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Mockery as m;

#[CoversClass(DeleteWithLimitTransactionFactory::class)]
class DeleteWithLimitTransactionFactoryTest extends TestCase
{
    private PostgreSQLIdentifierQuoter&m\MockInterface $quoter;
    private DeleteWithLimitTransactionFactory $deleteWithLimitTransactionFactory;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->quoter = m::mock(PostgreSQLIdentifierQuoter::class);

        $this->deleteWithLimitTransactionFactory = new DeleteWithLimitTransactionFactory($this->quoter);
    }

    #[Test]
    #[DataProvider('factoryDataProvider')]
    public function factory(bool $isIdempotent): void
    {
        $tableName = 'table';
        $identifierColumn = 'id';
        $identifierColumnType = PDO::PARAM_INT;
        $identifiers = [1, 2, 3];
        $limit = 2;

        $expected = new DeleteWithLimitTransaction(
            $this->quoter,
            $tableName,
            $identifierColumn,
            $identifierColumnType,
            $identifiers,
            $limit,
            $isIdempotent
        );

        $actual = $this->deleteWithLimitTransactionFactory->factory(
            $tableName,
            $identifierColumn,
            $identifierColumnType,
            $identifiers,
            $limit,
            $isIdempotent
        );

        self::assertEquals($expected, $actual);
    }

    public static function factoryDataProvider(): array
    {
        return [
            [
                'isIdempotent' => true,
            ],
            [
                'isIdempotent' => false,
            ]
        ];
    }
}
