<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('excerpt');
        });
    }

    public function down(): void
    {
        // Brings the column back so the step can be undone, but the removed ingress texts are gone for good.
        Schema::table('articles', function (Blueprint $table): void {
            $table->text('excerpt')->nullable()->after('content');
        });
    }
};
