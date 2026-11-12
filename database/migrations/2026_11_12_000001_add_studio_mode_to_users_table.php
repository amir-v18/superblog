<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Null means no choice yet, which keeps the default admin look; existing rows are left as they are.
        Schema::table('users', function (Blueprint $table) {
            $table->string('studio_mode', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('studio_mode');
        });
    }
};
