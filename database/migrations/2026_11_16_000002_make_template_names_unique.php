<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('templates', 'templates_name_unique', 'unique')) {
            return;
        }

        $duplicateNames = DB::table('templates')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        if ($duplicateNames->isNotEmpty()) {
            throw new RuntimeException('Cannot require unique template names while duplicates exist: '.$duplicateNames->implode(', '));
        }

        Schema::table('templates', function (Blueprint $table): void {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropUnique('templates_name_unique');
        });
    }
};
