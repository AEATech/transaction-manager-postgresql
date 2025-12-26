<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLTransactionsFactory;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLTransactionsFactoryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgreSQLTransactionsFactoryBuilder::class)]
class PostgreSQLTransactionsFactoryBuilderTest extends TestCase
{
    #[Test]
    public function build(): void
    {
        self::assertInstanceOf(PostgreSQLTransactionsFactory::class, PostgreSQLTransactionsFactoryBuilder::build());
    }
}
