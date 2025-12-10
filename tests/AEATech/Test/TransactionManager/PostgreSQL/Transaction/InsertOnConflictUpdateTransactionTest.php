<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTarget;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConstraintConflictTarget;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertOnConflictUpdateTransaction;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use InvalidArgumentException;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertOnConflictUpdateTransaction::class)]
class InsertOnConflictUpdateTransactionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertValuesBuilder&m\MockInterface $insertValuesBuilder;
    private PostgreSQLIdentifierQuoter $quoter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertValuesBuilder = m::mock(InsertValuesBuilder::class);
        $this->quoter = new PostgreSQLIdentifierQuoter();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function buildWithColumnsConflictTarget(): void
    {
        $rows = [
            ['id' => 1, 'na"me' => 'Alex'],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, ['id' => 1])
            ->andReturn([
                '(?, ?)',
                [1, 'Alex'],
                [0 => 1],
                ['id', 'na"me'],
            ]);

        $tx = new InsertOnConflictUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            'users',
            $rows,
            ['na"me'],
            new ColumnsConflictTarget($this->quoter, ['id']),
            ['id' => 1],
            true,
        );

        $q = $tx->build();

        $expectedSql = 'INSERT INTO "users" ("id", "na""me") VALUES (?, ?) ON CONFLICT ("id") DO UPDATE SET "na""me" = EXCLUDED."na""me"';

        self::assertSame($expectedSql, $q->sql);
        self::assertSame([1, 'Alex'], $q->params);
        self::assertSame([0 => 1], $q->types);
        self::assertTrue($tx->isIdempotent());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function buildWithConstraintConflictTarget(): void
    {
        $rows = [
            ['id' => 1, 'email' => 'a@example.com', 'name' => 'Alex'],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, [])
            ->andReturn([
                '(?, ?, ?)',
                [1, 'a@example.com', 'Alex'],
                [],
                ['id', 'email', 'name'],
            ]);

        $tx = new InsertOnConflictUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            'users',
            $rows,
            ['name'],
            new ConstraintConflictTarget($this->quoter, 'uniq_users_email'),
        );

        $q = $tx->build();

        $expectedSql = 'INSERT INTO "users" ("id", "email", "name") VALUES (?, ?, ?) ON CONFLICT ON CONSTRAINT "uniq_users_email" DO UPDATE SET "name" = EXCLUDED."name"';

        self::assertSame($expectedSql, $q->sql);
        self::assertSame([1, 'a@example.com', 'Alex'], $q->params);
        self::assertSame([], $q->types);
        self::assertFalse($tx->isIdempotent());
    }

    #[Test]
    public function constructorThrowsWhenUpdateColumnsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('InsertOnConflictUpdateTransaction requires non-empty $updateColumns.');

        new InsertOnConflictUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            't',
            [['a' => 1]],
            [],
            new ColumnsConflictTarget($this->quoter, ['a'])
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function buildThrowsWhenUpdateColumnMissingFromRows(): void
    {
        $rows = [
            ['id' => 1],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, [])
            ->andReturn([
                '(?)',
                [1],
                [],
                ['id'],
            ]);

        $tx = new InsertOnConflictUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            't',
            $rows,
            ['name'],
            new ColumnsConflictTarget($this->quoter, ['id'])
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update columns must exist in rows. Missing: name');

        $tx->build();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function buildThrowsWhenConflictColumnsMissingFromRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alex'],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, [])
            ->andReturn([
                '(?, ?)',
                [1, 'Alex'],
                [],
                ['id', 'name'],
            ]);

        $tx = new InsertOnConflictUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            't',
            $rows,
            ['name'],
            new ColumnsConflictTarget($this->quoter, ['email'])
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conflict columns must exist in rows. Missing: email');

        $tx->build();
    }

    #[Test]
    #[DataProvider('isIdempotentDataProvider')]
    public function isIdempotent(bool $isIdempotent): void
    {
        $rows = [['a' => 1, 'b' => 2]];

        $this->insertValuesBuilder->shouldReceive('build')->andReturn(['(?, ?)', [1, 2], [], ['a', 'b']]);

        $tx = new InsertOnConflictUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            't',
            $rows,
            ['b'],
            new ColumnsConflictTarget($this->quoter, ['a']),
            [],
            $isIdempotent,
        );

        self::assertSame($isIdempotent, $tx->isIdempotent());
    }

    public static function isIdempotentDataProvider(): array
    {
        return [
            ['isIdempotent' => true],
            ['isIdempotent' => false],
        ];
    }
}
