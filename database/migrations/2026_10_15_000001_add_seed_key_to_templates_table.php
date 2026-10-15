<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->string('seed_key')->nullable()->unique();
        });

        // Adopt the original seeded template on existing databases before its editable fields change.
        DB::table('templates')
            ->where('slug', 'editorial-default')
            ->whereNull('seed_key')
            ->update(['seed_key' => 'initial-editorial']);
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropUnique(['seed_key']);
            $table->dropColumn('seed_key');
        });
    }
};
