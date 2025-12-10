<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTarget;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnsConflictTarget::class)]
class ColumnsConflictTargetTest extends TestCase
{
    private PostgreSQLIdentifierQuoter $quoter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quoter = new PostgreSQLIdentifierQuoter();
    }

    #[Test]
    public function toSqlQuotesAllColumnsInOrder(): void
    {
        $target = new ColumnsConflictTarget($this->quoter, ['email', 'na"me']);

        self::assertSame('ON CONFLICT ("email", "na""me")', $target->toSql());
    }

    #[Test]
    public function validateAgainstColumnsSuccessWhenAllPresent(): void
    {
        $target = new ColumnsConflictTarget($this->quoter, ['email', 'name']);

        // should not throw
        $target->validateAgainstColumns(['id', 'email', 'name']);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function validateAgainstColumnsThrowsWhenMissing(): void
    {
        $target = new ColumnsConflictTarget($this->quoter, ['email']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conflict columns must exist in rows. Missing: email');
        $target->validateAgainstColumns(['id', 'name']);
    }

    #[Test]
    public function constructorThrowsOnEmptyColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ColumnsConflictTarget requires non-empty $columns.');

        new ColumnsConflictTarget($this->quoter, []);
    }
}
