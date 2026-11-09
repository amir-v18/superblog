<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('articles')
            ->select('rss_item_id')
            ->whereNotNull('rss_item_id')
            ->groupBy('rss_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException("Cannot enforce one article per RSS item: rss_item_id {$duplicate->rss_item_id} is linked to multiple articles.");
        }

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropForeign(['rss_item_id']);
            $table->unique('rss_item_id');
            $table->foreign('rss_item_id')->references('id')->on('rss_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropForeign(['rss_item_id']);
            $table->dropUnique(['rss_item_id']);
            $table->foreign('rss_item_id')->references('id')->on('rss_items')->nullOnDelete();
        });
    }
};
