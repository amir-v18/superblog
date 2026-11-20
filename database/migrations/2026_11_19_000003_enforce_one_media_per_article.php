<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('media')
            ->select('article_id')
            ->groupBy('article_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Cannot enforce one image per article while duplicate media records exist. Review the records before retrying.');
        }

        Schema::table('media', function (Blueprint $table): void {
            $table->unique('article_id', 'media_article_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique('media_article_id_unique');
        });
    }
};
