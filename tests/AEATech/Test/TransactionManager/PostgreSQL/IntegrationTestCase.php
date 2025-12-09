<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\PostgreSQL;

use AEATech\TransactionManager\ConnectionInterface;
use AEATech\TransactionManager\DoctrineAdapter\DbalConnectionAdapter;
use AEATech\TransactionManager\ExecutionPlanBuilder;
use AEATech\TransactionManager\GenericErrorClassifier;
use AEATech\TransactionManager\PostgreSQL\PostgreSQLErrorHeuristics;
use AEATech\TransactionManager\SystemSleeper;
use AEATech\TransactionManager\TransactionInterface;
use AEATech\TransactionManager\TransactionManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class IntegrationTestCase extends TestCase
{
    private static ?Connection $raw = null;
    private static ?DbalConnectionAdapter $adapter = null;
    private static ?TransactionManager $tm = null;

    /**
     * PHPUnit lifecycle: prepare shared instances.
     * Child classes can override but must call parent::setUpBeforeClass().
     */
    public static function setUpBeforeClass(): void
    {
        self::$raw = self::makeDbalConnection();
        self::$adapter = self::makeDbalConnectionAdapter(self::$raw);
        self::$tm = self::makeTransactionManager(self::$adapter);
    }

    /**
     * Clean the database before each test run.
     * Child classes may override to opt out or perform custom preparation, but should call parent::setUp().
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetDatabase();
    }

    /**
     * @throws Throwable
     */
    protected function runTransaction(
        TransactionInterface $transaction,
        TransactionInterface ...$nestedTransactions
    ): int {
        return self::tm()->run([$transaction, ...$nestedTransactions])->affectedRows;
    }

    /**
     * Universal DB reset for PostgreSQL: drop all tables in the current schema using CASCADE
     * so that foreign keys and dependent objects do not cause failures.
     *
     * @throws Throwable
     */
    private function resetDatabase(): void
    {
        $conn = self::db();

        $tables = $conn->fetchFirstColumn(
            'SELECT tablename FROM pg_tables WHERE schemaname = current_schema()'
        );

        foreach ($tables as $table) {
            $conn->executeStatement(
                'DROP TABLE IF EXISTS "' . str_replace('"', '""', $table) . '" CASCADE'
            );
        }
    }

    protected static function db(): Connection
    {
        if (!self::$raw) {
            self::$raw = self::makeDbalConnection();
        }

        return self::$raw;
    }

    protected static function adapter(): DbalConnectionAdapter
    {
        if (!self::$adapter) {
            self::$adapter = self::makeDbalConnectionAdapter(self::db());
        }

        return self::$adapter;
    }

    protected static function tm(): TransactionManager
    {
        if (!self::$tm) {
            self::$tm = self::makeTransactionManager(self::adapter());
        }

        return self::$tm;
    }

    /**
     * Creates Doctrine DBAL connection for tests.
     * Do not call directly, use db() for a shared instance.
     */
    protected static function makeDbalConnection(array $overrideParams = []): Connection
    {
        $params = [
            'driver' => 'pdo_pgsql',
            'host' => getenv('PGSQL_HOST'),
            'port' => $overrideParams['port'] ?? 5432,
            'dbname' => 'test',
            'user' => 'test',
            'password' => 'test',
            'charset' => 'utf8',
            'driverOptions' => [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ];

        $params = array_replace_recursive($params, $overrideParams);

        return DriverManager::getConnection($params, new Configuration());
    }

    private static function makeDbalConnectionAdapter(Connection $connection): DbalConnectionAdapter
    {
        return new DbalConnectionAdapter($connection);
    }

    private static function makeTransactionManager(ConnectionInterface $connection): TransactionManager
    {
        return new TransactionManager(
            new ExecutionPlanBuilder(),
            $connection,
            new GenericErrorClassifier(new PostgreSQLErrorHeuristics()),
            new SystemSleeper(),
        );
    }

    /**
     * PHPUnit lifecycle: close resources.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$raw) {
            try {
                self::$raw->close();
            } catch (Throwable) {
            }
        }

        self::$tm = null;
        self::$adapter = null;
        self::$raw = null;
    }
}
