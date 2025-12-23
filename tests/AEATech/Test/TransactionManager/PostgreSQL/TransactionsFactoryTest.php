<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL;

use AEATech\TransactionManager\PostgreSQL\TransactionsFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\DeleteWithLimitTransaction;
use AEATech\TransactionManager\PostgreSQL\Transaction\DeleteWithLimitTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertIgnoreTransaction;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertOnConflictUpdateTransaction;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertOnConflictUpdateTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTargetFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConstraintConflictTargetFactory;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\DeleteTransaction;
use AEATech\TransactionManager\Transaction\DeleteTransactionFactory;
use AEATech\TransactionManager\Transaction\InsertTransaction;
use AEATech\TransactionManager\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\Transaction\SqlTransaction;
use AEATech\TransactionManager\Transaction\UpdateTransaction;
use AEATech\TransactionManager\Transaction\UpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransaction;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransactionFactory;
use InvalidArgumentException;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionsFactory::class)]
class TransactionsFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertTransactionFactory&m\MockInterface $insertFactory;
    private InsertIgnoreTransactionFactory&m\MockInterface $insertIgnoreFactory;
    private InsertOnConflictUpdateTransactionFactory&m\MockInterface $upsertFactory;
    private DeleteTransactionFactory&m\MockInterface $deleteFactory;
    private DeleteWithLimitTransactionFactory&m\MockInterface $deleteWithLimitFactory;
    private UpdateTransactionFactory&m\MockInterface $updateFactory;
    private UpdateWhenThenTransactionFactory&m\MockInterface $updateWhenThenFactory;
    private TransactionsFactory $transactionsFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertFactory = m::mock(InsertTransactionFactory::class);
        $this->insertIgnoreFactory = m::mock(InsertIgnoreTransactionFactory::class);
        $this->upsertFactory = m::mock(InsertOnConflictUpdateTransactionFactory::class);
        $this->deleteFactory = m::mock(DeleteTransactionFactory::class);
        $this->deleteWithLimitFactory = m::mock(DeleteWithLimitTransactionFactory::class);
        $this->updateFactory = m::mock(UpdateTransactionFactory::class);
        $this->updateWhenThenFactory = m::mock(UpdateWhenThenTransactionFactory::class);

        $quoter = new PostgreSQLIdentifierQuoter();
        $columnsConflictTargetFactory = new ColumnsConflictTargetFactory($quoter);
        $constraintConflictTargetFactory = new ConstraintConflictTargetFactory($quoter);

        $this->transactionsFactory = new TransactionsFactory(
            insertTransactionFactory: $this->insertFactory,
            insertIgnoreTransactionFactory: $this->insertIgnoreFactory,
            insertOnConflictUpdateTransactionFactory: $this->upsertFactory,
            deleteTransactionFactory: $this->deleteFactory,
            deleteWithLimitTransactionFactory: $this->deleteWithLimitFactory,
            updateTransactionFactory: $this->updateFactory,
            updateWhenThenTransactionFactory: $this->updateWhenThenFactory,
            columnsConflictTargetFactory: $columnsConflictTargetFactory,
            constraintConflictTargetFactory: $constraintConflictTargetFactory,
        );
    }

    #[Test]
    public function createInsert(): void
    {
        $rows = [['id' => 1, 'name' => 'A']];
        $tx = m::mock(InsertTransaction::class);

        $this->insertFactory->shouldReceive('factory')
            ->once()
            ->with('users', $rows, ['id' => 1], true, StatementReusePolicy::None)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createInsert('users', $rows, ['id' => 1], true);

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createInsertIgnore(): void
    {
        $rows = [['id' => 2, 'name' => 'B']];
        $tx = m::mock(InsertIgnoreTransaction::class);

        $this->insertIgnoreFactory->shouldReceive('factory')
            ->once()
            ->with('t', $rows, [], false, StatementReusePolicy::None)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createInsertIgnore('t', $rows);

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createInsertOnConflictUpdate(): void
    {
        $rows = [['id' => 3, 'email' => 'x@example.com', 'name' => 'X']];
        $updateColumns = ['name'];
        $conflict = $this->transactionsFactory->conflictTargetByColumns(['email']);
        $tx = m::mock(InsertOnConflictUpdateTransaction::class);

        $this->upsertFactory->shouldReceive('factory')
            ->once()
            ->with('contacts', $rows, $updateColumns, $conflict, ['id' => 1], true, StatementReusePolicy::None)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createInsertOnConflictUpdate(
            'contacts',
            $rows,
            $updateColumns,
            $conflict,
            ['id' => 1],
            true
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createSql(): void
    {
        $sql = 'UPDATE t SET a = ? WHERE id IN (?, ?)';
        $params = [10, 1, 2];
        $types = [PDO::PARAM_INT, PDO::PARAM_INT, PDO::PARAM_INT];

        $expected = new SqlTransaction($sql, $params, $types, false, StatementReusePolicy::None);

        $sqlTransaction = $this->transactionsFactory->createSql($sql, $params, $types);

        self::assertEquals($expected, $sqlTransaction);
    }

    #[Test]
    public function createDelete(): void
    {
        $tx = m::mock(DeleteTransaction::class);

        $this->deleteFactory->shouldReceive('factory')
            ->once()
            ->with('logs', 'id', 1, [10, 11, 12], true, StatementReusePolicy::None)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createDelete(
            'logs',
            'id',
            1,
            [10, 11, 12],
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createDeleteWithLimit(): void
    {
        $tx = m::mock(DeleteWithLimitTransaction::class);

        $this->deleteWithLimitFactory->shouldReceive('factory')
            ->once()
            ->with('logs', 'id', 1, [10, 11, 12], 2, true, StatementReusePolicy::None)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createDeleteWithLimit(
            'logs',
            'id',
            1,
            [10, 11, 12],
            2
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createUpdate(): void
    {
        $tx = m::mock(UpdateTransaction::class);
        $columns = ['status' => 'archived'];

        $this->updateFactory->shouldReceive('factory')
            ->once()
            ->with('users', 'id', 1, [1, 2, 3], $columns, [], true, StatementReusePolicy::None)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createUpdate(
            'users',
            'id',
            1,
            [1, 2, 3],
            $columns
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createUpdateWhenThen(): void
    {
        $tx = m::mock(UpdateWhenThenTransaction::class);
        $rows = [
            ['id' => 10, 'status' => 'active', 'score' => 100],
            ['id' => 11, 'status' => 'blocked', 'score' => 200],
        ];
        $updateColumns = ['status', 'score'];
        $types = ['status' => null, 'score' => PDO::PARAM_INT];

        $this->updateWhenThenFactory->shouldReceive('factory')
            ->once()
            ->with('users', $rows, 'id', 1, $updateColumns, $types, true, StatementReusePolicy::None)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createUpdateWhenThen(
            'users',
            $rows,
            'id',
            1,
            $updateColumns,
            $types
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function conflictTargetByColumns(): void
    {
        $target = $this->transactionsFactory->conflictTargetByColumns(['email']);

        // SQL generation
        $sql = $target->toSql();
        self::assertSame('ON CONFLICT ("email")', $sql);

        // Validation success
        $target->validateAgainstColumns(['id', 'email', 'name']);

        // Validation failure
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conflict columns must exist in rows. Missing: email');
        $target->validateAgainstColumns(['id', 'name']);
    }

    #[Test]
    public function conflictTargetByConstraint(): void
    {
        $target = $this->transactionsFactory->conflictTargetByConstraint('uniq_email');
        $sql = $target->toSql();
        self::assertSame('ON CONFLICT ON CONSTRAINT "uniq_email"', $sql);
    }
}
