<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL;

use AEATech\TransactionManager\PostgreSQL\PostgreSQLErrorHeuristics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostgreSQLErrorHeuristics::class)]
class PostgreSQLErrorHeuristicsTest extends TestCase
{
    private PostgreSQLErrorHeuristics $heuristics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->heuristics = new PostgreSQLErrorHeuristics();
    }

    #[Test]
    public function isConnectionIssueBySqlState08Class(): void
    {
        self::assertTrue($this->heuristics->isConnectionIssue('08006', null, 'any'));
    }

    #[Test]
    public function isConnectionIssueBySqlState57P0xPrefix(): void
    {
        self::assertTrue($this->heuristics->isConnectionIssue('57P01', null, 'admin shutdown'));
    }

    #[Test]
    #[DataProvider('connectionIssueMessageDataProvider')]
    public function isConnectionIssueByMessageSubstring(string $message): void
    {
        self::assertTrue($this->heuristics->isConnectionIssue(null, null, $message));
    }

    public static function connectionIssueMessageDataProvider(): array
    {
        return [
            'server closed unexpectedly' => ['server closed the connection unexpectedly'],
            'admin termination' => ['terminating connection due to administrator command'],
            'idle in tx timeout' => ['terminating connection due to idle-in-transaction timeout'],
            'receive data' => ['could not receive data from server: Connection reset by peer'],
            'send data' => ['could not send data to server: Broken pipe'],
            'connection refused' => ['connection to server at "127.0.0.1", port 5432 failed: Connection refused'],
            'no route to host' => ['no route to host'],
            'ssl syscall' => ['SSL SYSCALL error: EOF detected'],
            'eof detected' => ['EOF detected'],
            'broken pipe' => ['broken pipe'],
            'reset by peer' => ['connection reset by peer'],
            'starting up' => ['the database system is starting up'],
            'shutting down' => ['the database system is shutting down'],
        ];
    }

    #[Test]
    public function isNotConnectionIssueWhenNoSignals(): void
    {
        self::assertFalse($this->heuristics->isConnectionIssue('42P01', null, 'relation "x" does not exist'));
    }

    #[Test]
    public function isTransientIssueBySqlStates(): void
    {
        self::assertTrue($this->heuristics->isTransientIssue('40001', null, 'serialization failure'));
        self::assertTrue($this->heuristics->isTransientIssue('40P01', null, 'deadlock detected'));
        // ensure case-insensitivity for custom inputs
        self::assertTrue($this->heuristics->isTransientIssue('55p03', null, 'lock not available'));
    }

    #[Test]
    #[DataProvider('transientIssueMessageDataProvider')]
    public function isTransientIssueByMessageSubstring(string $message): void
    {
        self::assertTrue($this->heuristics->isTransientIssue(null, null, $message));
    }

    public static function transientIssueMessageDataProvider(): array
    {
        return [
            'deadlock' => ['deadlock detected'],
            'serialization' => ['could not serialize access due to read/write dependencies among transactions'],
            'lock timeout' => ['canceling statement due to lock timeout'],
            'lock not available' => ['lock not available'],
            'recovery conflict' => ['canceling statement due to conflict with recovery'],
            'could not obtain lock on' => ['could not obtain lock on relation 12345 of database 1'],
        ];
    }

    #[Test]
    public function isNotTransientIssueWhenNoSignals(): void
    {
        self::assertFalse($this->heuristics->isTransientIssue('42883', null, 'function xyz(integer) does not exist'));
    }
}
