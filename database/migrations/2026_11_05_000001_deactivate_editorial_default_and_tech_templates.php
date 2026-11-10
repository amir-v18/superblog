<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Only Modern magasin and Klassisk tidning are offered from now on. The old templates are deactivated,
 * not deleted: magazines may still use them, and the foreign key on superblogs.template_id blocks deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Missing rows simply match nothing. Only active rows are touched, so a rerun changes nothing,
        // not even updated_at.
        DB::table('templates')
            ->whereIn('slug', ['editorial-default', 'tech'])
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Deliberately empty. Which rows were active before is not recorded, and switching them on again
        // could bring back a template that an admin had deactivated on purpose. Reactivate by hand in admin.
    }
};
