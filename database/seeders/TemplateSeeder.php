<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Creates the templates in config/templates.php that are missing from the database, matched on immutable seed_key.
 *
 * Existing rows are never changed. Unkeyed legacy rows must be explicitly mapped before seeding, so
 * the seeder cannot mistake an existing template for a missing one and create a duplicate.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $unkeyedIds = Template::whereNull('seed_key')->orderBy('id')->pluck('id');

        if ($unkeyedIds->isNotEmpty()) {
            throw new \RuntimeException('Templates without a stable seed key must be explicitly mapped before seeding. Unmapped template IDs: '.$unkeyedIds->implode(', '));
        }

        foreach (config('templates.list', []) as $seedKey => $entry) {
            // seed_key is intentionally not mass assignable; this trusted catalog is the only creation path.
            Template::unguarded(fn () => Template::firstOrCreate(['seed_key' => $seedKey], [
                'name' => $entry['name'],
                // Retained only because the legacy column is still required; application behavior never reads it.
                'slug' => $seedKey,
                'description' => $entry['description'],
                // The generic page layout, the only one Template::availableLayouts() allows. The site itself
                // is picked by 'site' in the registry, not by this column.
                'layout' => 'layouts.public',
                'is_active' => true,
            ]));
        }
    }
}
