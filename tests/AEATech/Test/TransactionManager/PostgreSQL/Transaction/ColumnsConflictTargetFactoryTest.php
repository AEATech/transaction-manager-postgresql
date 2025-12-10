<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL\Transaction;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLIdentifierQuoter;
use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTarget;
use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTargetFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnsConflictTargetFactory::class)]
class ColumnsConflictTargetFactoryTest extends TestCase
{
    private ColumnsConflictTargetFactory $columnsConflictTargetFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->columnsConflictTargetFactory = new ColumnsConflictTargetFactory(new PostgreSQLIdentifierQuoter());
    }

    #[Test]
    public function factoryCreatesTargetAndQuotesColumns(): void
    {
        $target = $this->columnsConflictTargetFactory->factory(['email', 'na"me']);

        self::assertInstanceOf(ColumnsConflictTarget::class, $target);
        self::assertSame('ON CONFLICT ("email", "na""me")', $target->toSql());
    }

    #[Test]
    public function factoryThrowsWhenEmptyColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ColumnsConflictTarget requires non-empty $columns.');

        $this->columnsConflictTargetFactory->factory([]);
    }
}
