<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConstraintConflictTarget;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConstraintConflictTarget::class)]
class ConstraintConflictTargetTest extends TestCase
{
    private PostgreSQLIdentifierQuoter $quoter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quoter = new PostgreSQLIdentifierQuoter();
    }

    #[Test]
    public function toSqlQuotesConstraintName(): void
    {
        $target = new ConstraintConflictTarget($this->quoter, 'uniq_user-email');

        self::assertSame('ON CONFLICT ON CONSTRAINT "uniq_user-email"', $target->toSql());
    }

    #[Test]
    public function validateAgainstColumnsIsNoopAndDoesNotThrow(): void
    {
        $target = new ConstraintConflictTarget($this->quoter, 'uniq_email');

        // should not throw for any columns set
        $target->validateAgainstColumns([]);
        $target->validateAgainstColumns(['id']);
        $target->validateAgainstColumns(['id', 'email']);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function constructorThrowsOnEmptyConstraintName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ConstraintConflictTarget requires non-empty $constraintName.');

        new ConstraintConflictTarget($this->quoter, '');
    }
}
