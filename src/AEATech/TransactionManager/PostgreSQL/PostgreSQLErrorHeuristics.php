<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL;

use AEATech\TransactionManager\DatabaseErrorHeuristicsInterface;

class PostgreSQLErrorHeuristics implements DatabaseErrorHeuristicsInterface
{
    // SQLSTATE class 08 — connection exceptions (SQL standard)
    // Also include 57P0x (operator intervention leading to disconnect/unavailable),
    // e.g., 57P01 admin_shutdown, 57P02 crash_shutdown, 57P03 cannot_connect_now
    public const DEFAULT_SQLSTATE_CONNECTION_PREFIXES = [
        '08',
        '57P0',
    ];

    // For PostgreSQL, we primarily rely on SQLSTATEs and message heuristics; numeric driver codes are
    // uncommon in PDO/DBAL for pgsql, but keep the structure consistent with MySQL implementation.
    public const DEFAULT_CONNECTION_MESSAGE_SUBSTRINGS = [
        // Common PG connection loss indicators
        'server closed the connection unexpectedly',
        'terminating connection due to administrator command',
        'terminating connection due to idle-in-transaction timeout',
        'could not receive data from server',
        'could not send data to server',
        'connection to server at', // often combined with details like "refused" or timeout
        'connection refused',
        'no route to host',
        'ssl syscall error',
        'eof detected',
        'broken pipe',
        'connection reset by peer',
        'the database system is starting up',
        'the database system is shutting down',
    ];

    // Transient/Retryable SQLSTATEs for PostgreSQL
    // 40001 — serialization_failure; 40P01 — deadlock_detected; 55P03 — lock_not_available
    public const DEFAULT_TRANSIENT_SQL_STATES = ['40001', '40P01', '55P03'];

    public const DEFAULT_TRANSIENT_MESSAGE_SUBSTRINGS = [
        'deadlock detected',
        'could not serialize access',
        'canceling statement due to lock timeout',
        'lock not available',
        'canceling statement due to conflict with recovery',
        'could not obtain lock on',
    ];

    private array $sqlstateConnectionPrefixes;
    private array $connectionMsgNeedles;
    private array $transientSqlStates;
    private array $transientMsgNeedles;

    /**
     * @param string[]|null $sqlstateConnectionPrefixes
     * @param string[]|null $connectionMsgNeedles
     * @param string[]|null $transientSqlStates
     * @param string[]|null $transientMsgNeedles
     */
    public function __construct(
        ?array $sqlstateConnectionPrefixes = null,
        ?array $connectionMsgNeedles = null,
        ?array $transientSqlStates = null,
        ?array $transientMsgNeedles = null
    ) {
        // Normalize all inputs to lower-case to enable case-insensitive checks.
        $this->sqlstateConnectionPrefixes = array_map(
            'strtolower',
            $sqlstateConnectionPrefixes ?? self::DEFAULT_SQLSTATE_CONNECTION_PREFIXES
        );

        $this->connectionMsgNeedles = array_map(
            'strtolower',
            $connectionMsgNeedles ?? self::DEFAULT_CONNECTION_MESSAGE_SUBSTRINGS
        );

        $this->transientSqlStates = array_map(
            'strtolower',
            $transientSqlStates ?? self::DEFAULT_TRANSIENT_SQL_STATES
        );

        $this->transientMsgNeedles = array_map(
            'strtolower',
            $transientMsgNeedles ?? self::DEFAULT_TRANSIENT_MESSAGE_SUBSTRINGS
        );
    }

    public function isConnectionIssue(?string $sqlState, ?int $driverCode, string $message): bool
    {
        $msg = strtolower($message);

        if ($sqlState !== null) {
            $state = strtolower($sqlState);
            foreach ($this->sqlstateConnectionPrefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($state, $prefix)) {
                    return true;
                }
            }
        }

        // Heuristic by message substrings
        foreach ($this->connectionMsgNeedles as $n) {
            if (str_contains($msg, $n)) {
                return true;
            }
        }

        return false;
    }

    public function isTransientIssue(?string $sqlState, ?int $driverCode, string $message): bool
    {
        $msg = strtolower($message);

        if ($sqlState !== null && in_array(strtolower($sqlState), $this->transientSqlStates, true)) {
            return true;
        }

        foreach ($this->transientMsgNeedles as $n) {
            if (str_contains($msg, $n)) {
                return true;
            }
        }

        return false;
    }
}
