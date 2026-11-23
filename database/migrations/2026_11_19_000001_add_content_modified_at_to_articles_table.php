<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            // Existing publication dates may already have been changed by earlier withdrawals or republishing.
            // Leave them untouched and start tracking reader-visible edits from this migration onward.
            $table->timestamp('content_modified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('content_modified_at');
        });
    }
};
