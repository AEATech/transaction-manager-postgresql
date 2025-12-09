<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL;

use AEATech\TransactionManager\ErrorType;
use AEATech\TransactionManager\GenericErrorClassifier;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLErrorHeuristics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(PostgreSQLErrorHeuristics::class)]
#[CoversClass(GenericErrorClassifier::class)]
class PostgreSQLErrorHeuristicsIntegrationTest extends IntegrationTestCase
{
    private GenericErrorClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new GenericErrorClassifier(new PostgreSQLErrorHeuristics());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function lockTimeoutIsTransient(): void
    {
        $thrown = $this->simulatePgException('55P03');

        self::assertSame(
            ErrorType::Transient,
            $this->classifier->classify($thrown),
            'Lock timeout (55P03) must be classified as transient'
        );
    }

    /**
     * Real deadlock between two concurrent transactions should be classified as Transient (40P01).
     * @throws Throwable
     */
    #[Test]
    public function deadlockIsTransient(): void
    {
        $deadlock = $this->simulatePgException('40P01');

        self::assertSame(
            ErrorType::Transient,
            $this->classifier->classify($deadlock),
            'Deadlock (40P01) must be classified as transient'
        );
    }

    /**
     * Unique constraint violation should be classified as Fatal (permanent).
     * @throws Throwable
     */
    #[Test]
    public function uniqueViolationIsFatal(): void
    {
        self::db()->executeStatement(<<<'SQL'
CREATE TABLE tm_unique_test (
    id INT PRIMARY KEY
)
SQL
        );

        self::db()->executeStatement("DELETE FROM tm_unique_test");
        self::db()->executeStatement("INSERT INTO tm_unique_test (id) VALUES (1)");

        $thrown = null;
        try {
            self::db()->executeStatement("INSERT INTO tm_unique_test (id) VALUES (1)");
        } catch (Throwable $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(Throwable::class, $thrown, 'Expected a unique violation error');
        self::assertSame(ErrorType::Fatal, $this->classifier->classify($thrown));
    }

    /**
     * Connection error on an invalid port should be classified as Connection.
     */
    #[Test]
    public function connectionErrorOnInvalidPortIsConnection(): void
    {
        $thrown = null;

        try {
            // Use an obviously invalid/unreachable port
            $conn = self::makeDbalConnection(['port' => 65432]);
            $conn->executeStatement('SELECT 1');
        } catch (Throwable $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(Throwable::class, $thrown, 'Expected a connection error');
        self::assertSame(ErrorType::Connection, $this->classifier->classify($thrown));
    }

    private function simulatePgException(string $sqlState): Throwable
    {
        $exception = null;

        try {
            self::db()->executeStatement(<<<SQL
DO $$
BEGIN
    RAISE EXCEPTION 'simulated error with SQLSTATE {$sqlState}'
        USING ERRCODE = '{$sqlState}';
END$$;
SQL
            );
        } catch (Throwable $e) {
            $exception = $e;
        }

        self::assertInstanceOf(Throwable::class, $exception, "Expected an exception for SQLSTATE {$sqlState}");

        return $exception;
    }
}
