<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            // Existing HTML rows remain untouched and are explicitly identified as legacy.
            $table->string('content_format', 32)->default('legacy_html')->after('content');
        });
    }

    public function down(): void
    {
        // Keep the format marker if structured documents have been written; otherwise rollback
        // would make their JSON indistinguishable from legacy article HTML.
        if (DB::table('articles')->where('content_format', '!=', 'legacy_html')->exists()) {
            throw new RuntimeException('Cannot remove content_format while structured article documents exist.');
        }

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('content_format');
        });
    }
};
