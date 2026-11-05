<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Creates the templates in config/templates.php that are missing from the database, matched on slug.
 *
 * Rows that already exist are never changed: admins may have edited their name, description or
 * is_active, and the database is the truth for those. Safe to run again at any time.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('templates.list', []) as $slug => $entry) {
            // The second array is only used when the row is created.
            Template::firstOrCreate(['slug' => $slug], [
                'name' => $entry['name'],
                'description' => $entry['description'],
                // The generic page layout, the only one Template::availableLayouts() allows. The site itself
                // is picked by 'site' in the registry, not by this column.
                'layout' => 'layouts.public',
                'is_active' => true,
            ]);
        }
    }
}
