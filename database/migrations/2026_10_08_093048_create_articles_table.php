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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('superblog_id')->constrained()->cascadeOnDelete();
            // Manual articles have no RSS item; deleting an item must preserve its articles.
            $table->foreignId('rss_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('content');
            $table->text('excerpt')->nullable();
            // The original source is rss or manual; AI is a processing mechanism.
            $table->string('source_type');
            $table->string('status')->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unique(['superblog_id', 'slug']);
            $table->index(['superblog_id', 'status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
