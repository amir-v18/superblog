<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superblog_user')) {
            // Separate DDL steps make each possible MySQL interruption recoverable.
            Schema::create('superblog_user', function (Blueprint $table) {
                $table->foreignId('superblog_id');
                $table->foreignId('user_id');
            });
        }

        $this->validateStructure();

        if (! Schema::hasIndex('superblog_user', ['superblog_id', 'user_id'], 'primary')) {
            Schema::table('superblog_user', fn (Blueprint $table) => $table->primary(['superblog_id', 'user_id']));
        }

        if (! Schema::hasIndex('superblog_user', ['user_id'])) {
            Schema::table('superblog_user', fn (Blueprint $table) => $table->index('user_id'));
        }

        foreach (['superblog_id' => 'superblogs', 'user_id' => 'users'] as $column => $parent) {
            $exists = collect(Schema::getForeignKeys('superblog_user'))->contains(
                fn (array $key) => $key['columns'] === [$column]
            );

            if (! $exists) {
                Schema::table('superblog_user', fn (Blueprint $table) => $table->foreign($column)
                    ->references('id')->on($parent)->cascadeOnDelete());
            }
        }

        $this->validateStructure();

        // Pause ownership/assignment writes until completion; retries preserve existing rows.
        DB::table('superblogs')->orderBy('id')->chunkById(500, function ($superblogs) {
            DB::table('superblog_user')->upsert($superblogs->map(fn ($superblog) => [
                'superblog_id' => $superblog->id,
                'user_id' => $superblog->user_id,
            ])->all(), ['superblog_id', 'user_id'], ['superblog_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('superblog_user')) {
            $this->validateStructure();
            // Rollback discards all assignments, including those added after migration.
            Schema::drop('superblog_user');
        }
    }

    private function validateStructure(): void
    {
        $columns = collect(Schema::getColumns('superblog_user'))->keyBy('name');

        if ($columns->keys()->sort()->values()->all() !== ['superblog_id', 'user_id']) {
            throw new RuntimeException('Unexpected superblog_user columns; inspect the schema before retrying.');
        }

        foreach (['superblog_id' => 'superblogs', 'user_id' => 'users'] as $name => $parent) {
            $column = $columns[$name];
            $id = collect(Schema::getColumns($parent))->firstWhere('name', 'id');

            if (strtolower($column['type']) !== strtolower($id['type']) || $column['nullable']
                || $column['default'] !== null || $column['auto_increment'] || $column['generation'] !== null) {
                throw new RuntimeException("Unexpected superblog_user.{$name} definition; expected the parent ID type.");
            }

            if (DB::table('superblog_user')->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from($parent)->whereColumn("{$parent}.id", "superblog_user.{$name}"))->exists()) {
                throw new RuntimeException("Orphan superblog_user.{$name} values; repair them before retrying.");
            }
        }

        if (DB::table('superblog_user')->groupBy('superblog_id', 'user_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate superblog_user assignments; inspect them before retrying.');
        }

        foreach (Schema::getIndexes('superblog_user') as $index) {
            $valid = $index['primary']
                ? $index['columns'] === ['superblog_id', 'user_id']
                : ! $index['unique'] && in_array($index['columns'], [['user_id'], ['superblog_id']]);

            if (! $valid || (DB::connection()->getDriverName() === 'mysql' && $index['type'] !== 'btree')) {
                throw new RuntimeException('Unexpected superblog_user index; inspect it before retrying.');
            }
        }

        $seen = [];
        foreach (Schema::getForeignKeys('superblog_user') as $key) {
            $parent = match ($key['columns']) {
                ['superblog_id'] => 'superblogs',
                ['user_id'] => 'users',
                default => null,
            };
            $validSchema = DB::connection()->getDriverName() !== 'mysql'
                || $key['foreign_schema'] === DB::connection()->getDatabaseName();

            if ($parent === null || isset($seen[$parent]) || ! $validSchema
                || $key['foreign_table'] !== $parent || $key['foreign_columns'] !== ['id']
                || $key['on_delete'] !== 'cascade' || ! in_array($key['on_update'], ['no action', 'restrict'])) {
                throw new RuntimeException('Unexpected superblog_user foreign key; inspect it before retrying.');
            }

            $seen[$parent] = true;
        }
    }
};
