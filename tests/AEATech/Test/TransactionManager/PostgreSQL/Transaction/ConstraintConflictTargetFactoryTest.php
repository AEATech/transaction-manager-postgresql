<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConstraintConflictTarget;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConstraintConflictTargetFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConstraintConflictTargetFactory::class)]
class ConstraintConflictTargetFactoryTest extends TestCase
{
    private ConstraintConflictTargetFactory $constraintConflictTargetFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->constraintConflictTargetFactory = new ConstraintConflictTargetFactory(new PostgreSQLIdentifierQuoter());
    }

    #[Test]
    public function factoryCreatesTargetAndQuotesConstraint(): void
    {
        $target = $this->constraintConflictTargetFactory->factory('uniq_user-email');

        self::assertInstanceOf(ConstraintConflictTarget::class, $target);
        self::assertSame('ON CONFLICT ON CONSTRAINT "uniq_user-email"', $target->toSql());
    }

    #[Test]
    public function factoryThrowsOnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ConstraintConflictTarget requires non-empty $constraintName.');

        $this->constraintConflictTargetFactory->factory('');
    }
}
