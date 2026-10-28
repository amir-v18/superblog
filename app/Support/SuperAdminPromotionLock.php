<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class SuperAdminPromotionLock
{
    public function acquire(): void
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() === 0) {
            throw new RuntimeException('The super-admin lock must be acquired inside a transaction.');
        }

        $driver = $connection->getDriverName();
        if ($driver !== 'mysql' && ! ($driver === 'sqlite' && app()->environment('testing'))) {
            throw new RuntimeException('Super-admin changes require MySQL/InnoDB locking.');
        }

        if (! Schema::hasTable('migrations')) {
            throw new RuntimeException('The migrations table required for the super-admin lock is missing.');
        }

        if ($driver === 'mysql') {
            try {
                $engine = $connection->selectOne(
                    'select engine from information_schema.tables where table_schema = ? and table_name = ?',
                    [$connection->getDatabaseName(), $connection->getTablePrefix().'migrations'],
                )?->engine;
            } catch (Throwable $exception) {
                throw new RuntimeException('Could not verify the super-admin lock table engine.', previous: $exception);
            }

            if (strtolower((string) $engine) !== 'innodb') {
                throw new RuntimeException('The migrations table must use InnoDB for the super-admin lock.');
            }
        }

        try {
            $lockRow = DB::table('migrations')->orderBy('id')->lockForUpdate()->first();
        } catch (Throwable $exception) {
            throw new RuntimeException('Could not acquire the super-admin lock.', previous: $exception);
        }

        if (! $lockRow) {
            throw new RuntimeException('The migrations table has no row available for the super-admin lock.');
        }
    }
}
