<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\PostgreSQL;

use AEATech\TransactionManager\PostgreSQL\Transaction\ColumnsConflictTargetFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\ConstraintConflictTargetFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\DeleteWithLimitTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\PostgreSQL\Transaction\InsertOnConflictUpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\DeleteTransactionFactory;
use AEATech\TransactionManager\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\Transaction\Internal\UpdateWhenThenDefinitionsBuilder;
use AEATech\TransactionManager\Transaction\UpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransactionFactory;

class PostgreSQLTransactionsFactoryBuilder
{
    public static function build(): PostgreSQLTransactionsFactoryInterface
    {
        $quoter = new PostgreSQLIdentifierQuoter();
        $insertValuesBuilder = new InsertValuesBuilder();
        $updateWhenThenDefinitionsBuilder = new UpdateWhenThenDefinitionsBuilder();

        return new PostgreSQLTransactionsFactory(
            insertTransactionFactory: new InsertTransactionFactory($insertValuesBuilder, $quoter),
            insertIgnoreTransactionFactory: new InsertIgnoreTransactionFactory($insertValuesBuilder, $quoter),
            insertOnConflictUpdateTransactionFactory: new InsertOnConflictUpdateTransactionFactory(
                $insertValuesBuilder,
                $quoter
            ),
            deleteTransactionFactory: new DeleteTransactionFactory($quoter),
            deleteWithLimitTransactionFactory: new DeleteWithLimitTransactionFactory($quoter),
            updateTransactionFactory: new UpdateTransactionFactory($quoter),
            updateWhenThenTransactionFactory: new UpdateWhenThenTransactionFactory(
                $updateWhenThenDefinitionsBuilder,
                $quoter
            ),
            columnsConflictTargetFactory: new ColumnsConflictTargetFactory($quoter),
            constraintConflictTargetFactory: new ConstraintConflictTargetFactory($quoter),
        );
    }
}
