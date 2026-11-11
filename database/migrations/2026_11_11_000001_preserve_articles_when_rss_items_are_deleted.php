<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropForeign(['rss_item_id']);
            $table->foreign('rss_item_id')->references('id')->on('rss_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropForeign(['rss_item_id']);
            $table->foreign('rss_item_id')->references('id')->on('rss_items')->cascadeOnDelete();
        });
    }
};
