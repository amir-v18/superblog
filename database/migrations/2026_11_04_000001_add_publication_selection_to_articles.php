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
            $table->boolean('is_selected_for_publication')->default(false);
        });

        // Existing published content starts selected; drafts stay out of the next publication by default.
        DB::table('articles')
            ->where('status', 'published')
            ->update(['is_selected_for_publication' => true]);
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('is_selected_for_publication');
        });
    }
};
