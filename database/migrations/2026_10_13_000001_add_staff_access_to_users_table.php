<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Validate before changing anything; existence alone does not prove compatibility.
        $this->validateColumns();

        $this->checkpointUsers();

        foreach (['is_super_admin', 'has_all_superblogs_access'] as $name) {
            if (! Schema::hasColumn('users', $name)) {
                Schema::table('users', fn (Blueprint $table) => $table->boolean($name)->default(false));
            }
        }

        if (! Schema::hasColumn('users', 'approval_status')) {
            // NULL identifies undecided originals; the persisted snapshot excludes later inserts.
            Schema::table('users', fn (Blueprint $table) => $table->string('approval_status')->nullable());
        }

        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'approval_status');
        if ($column['nullable']) {
            // Change the default before backfilling: later inserts are pending, not markers.
            Schema::table('users', fn (Blueprint $table) => $table->string('approval_status')
                ->nullable()->default('pending')->change());

            DB::transaction(function () {
                DB::table('users')->whereNull('approval_status')
                    ->whereIn('id', DB::table('staff_approval_backfill')->select('user_id')->where('user_id', '>', 0))
                    ->update(['approval_status' => 'approved']);
            });

            DB::table('users')->whereNull('approval_status')->update(['approval_status' => 'pending']);
            Schema::table('users', fn (Blueprint $table) => $table->string('approval_status')
                ->nullable(false)->default('pending')->change());
        }

        $this->validateColumns();
    }

    public function down(): void
    {
        $this->validateColumns();

        // Keep the checkpoint: Laravel records success only after up/down returns.
        // Dropped approvals and privileges cannot be reconstructed by migrating again.
        foreach (['approval_status', 'has_all_superblogs_access', 'is_super_admin'] as $name) {
            if (Schema::hasColumn('users', $name)) {
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn($name));
            }
        }
    }

    private function checkpointUsers(): void
    {
        $table = 'staff_approval_backfill';
        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->unsignedBigInteger('user_id')->primary();
            });
        }

        $columns = Schema::getColumns($table);
        $indexes = Schema::getIndexes($table);
        if (count($columns) !== 1 || $columns[0]['name'] !== 'user_id'
            || ! in_array(strtolower($columns[0]['type']), ['bigint unsigned', 'integer'])
            || $columns[0]['nullable'] || $columns[0]['default'] !== null
            || (DB::connection()->getDriverName() === 'mysql' && $columns[0]['auto_increment'])
            || $columns[0]['generation'] !== null
            || count($indexes) !== 1 || ! $indexes[0]['primary']
            || $indexes[0]['columns'] !== ['user_id'] || Schema::getForeignKeys($table) !== []) {
            throw new RuntimeException('Unexpected staff approval checkpoint schema.');
        }

        if (DB::connection()->getDriverName() === 'mysql'
            && strtolower(DB::table('information_schema.tables')->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', DB::connection()->getTablePrefix().$table)->value('engine') ?? '') !== 'innodb') {
            throw new RuntimeException('The staff approval checkpoint requires InnoDB transactions.');
        }

        if (DB::table($table)->where('user_id', 0)->exists()) {
            return;
        }

        // Without an earlier snapshot, an old partially applied migration is ambiguous.
        if (array_intersect(Schema::getColumnListing('users'), ['is_super_admin', 'has_all_superblogs_access', 'approval_status'])
            || DB::table($table)->exists()) {
            throw new RuntimeException('Missing staff approval checkpoint; recover the original population before retrying.');
        }

        // Run one migrator at a time; do not recycle IDs or explicitly insert historical IDs.
        // MAX(id) is insufficient: a lower allocated ID can commit after the snapshot.
        DB::transaction(function () use ($table) {
            // Zero is a durable completion marker, not a user ID. No FK: keep history on deletion.
            if (DB::table('users')->where('id', '<=', 0)->exists()) {
                throw new RuntimeException('Staff approval checkpoint requires positive user IDs.');
            }
            DB::table($table)->insertUsing(['user_id'], DB::table('users')->select('id'));
            DB::table($table)->insert(['user_id' => 0]);
        });
    }

    private function validateColumns(): void
    {
        foreach (Schema::getColumns('users') as $column) {
            $name = $column['name'];

            if (! in_array($name, ['is_super_admin', 'has_all_superblogs_access', 'approval_status'])) {
                continue;
            }

            // SQLite quotes literal defaults; MySQL returns the literal without quotes.
            $default = $column['default'] === null ? null : (string) $column['default'];
            if (DB::connection()->getDriverName() === 'sqlite' && $default !== null
                && str_starts_with($default, "'") && str_ends_with($default, "'")) {
                $default = str_replace("''", "'", substr($default, 1, -1));
            }
            $plainColumn = ! $column['auto_increment'] && $column['generation'] === null;

            if ($name === 'approval_status') {
                $type = DB::connection()->getDriverName() === 'sqlite' ? 'varchar' : 'varchar(255)';
                $valid = $plainColumn && strtolower($column['type']) === $type
                    && (($column['nullable'] && in_array($default, [null, 'pending'], true))
                        || (! $column['nullable'] && $default === 'pending'));
            } else {
                $valid = $plainColumn && in_array(strtolower($column['type']), ['tinyint(1)', 'tinyint', 'boolean'])
                    && ! $column['nullable'] && $default === '0';
            }

            if (! $valid) {
                throw new RuntimeException("Unexpected users.{$name} definition; inspect the schema before retrying.");
            }
        }

        $staffColumns = ['is_super_admin', 'has_all_superblogs_access', 'approval_status'];
        foreach (array_merge(Schema::getIndexes('users'), Schema::getForeignKeys('users')) as $constraint) {
            if (array_intersect($staffColumns, $constraint['columns'])) {
                throw new RuntimeException('Unexpected constraint on staff access columns.');
            }
        }

        if (Schema::hasColumn('users', 'approval_status') && DB::table('users')
            ->whereNotNull('approval_status')->select('approval_status')->cursor()
            ->contains(fn ($user) => ! in_array($user->approval_status, ['pending', 'approved', 'rejected'], true))) {
            throw new RuntimeException('Unexpected approval status values; inspect them before retrying.');
        }
    }
};
