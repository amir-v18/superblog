<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rss_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rss_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('guid')->nullable();
            $table->string('title');
            $table->text('url');
            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            // Keep original source material separate from transformed article content.
            $table->longText('content')->nullable();
            $table->text('summary')->nullable();
            $table->string('content_hash', 64)->nullable()->index();
            $table->index(['rss_source_id', 'external_id']);
            $table->index(['rss_source_id', 'guid']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rss_items');
    }
};
