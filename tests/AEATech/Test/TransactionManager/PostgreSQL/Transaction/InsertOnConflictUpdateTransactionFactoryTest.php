<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTarget;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertOnConflictUpdateTransactionFactory;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertOnConflictUpdateTransactionFactory::class)]
class InsertOnConflictUpdateTransactionFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertValuesBuilder&m\MockInterface $insertValuesBuilder;
    private InsertOnConflictUpdateTransactionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertValuesBuilder = m::mock(InsertValuesBuilder::class);
        $this->factory = new InsertOnConflictUpdateTransactionFactory(
            $this->insertValuesBuilder,
            new PostgreSQLIdentifierQuoter(),
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function factoryCreatesTransactionAndBuildsExpectedSql(): void
    {
        $rows = [
            ['id' => 1, 'email' => 'a@example.com', 'name' => 'Alex'],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, ['id' => 1])
            ->andReturn([
                '(?, ?, ?)',
                [1, 'a@example.com', 'Alex'],
                [0 => 1],
                ['id', 'email', 'name'],
            ]);

        $conflict = new ColumnsConflictTarget(new PostgreSQLIdentifierQuoter(), ['email']);

        $tx = $this->factory->factory(
            'users',
            $rows,
            ['name'],
            $conflict,
            ['id' => 1],
            true,
            StatementReusePolicy::PerTransaction
        );

        $q = $tx->build();

        $expectedSql = 'INSERT INTO "users" ("id", "email", "name") VALUES (?, ?, ?) ON CONFLICT ("email") DO UPDATE SET "name" = EXCLUDED."name"';

        self::assertSame($expectedSql, $q->sql);
        self::assertSame([1, 'a@example.com', 'Alex'], $q->params);
        self::assertSame([0 => 1], $q->types);
        self::assertTrue($tx->isIdempotent());
        self::assertSame(StatementReusePolicy::PerTransaction, $q->statementReusePolicy);
    }
}
