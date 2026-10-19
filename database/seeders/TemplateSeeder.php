<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    private const SEED_KEY = 'initial-editorial';

    public function run(): void
    {
        if (Template::where('seed_key', self::SEED_KEY)->exists()) {
            return;
        }

        // Claim the original record created before seed_key existed, without relying on editable fields afterward.
        $legacyTemplate = Template::where('slug', 'editorial-default')->first();

        if ($legacyTemplate) {
            $legacyTemplate->forceFill(['seed_key' => self::SEED_KEY])->save();

            return;
        }

        $template = new Template([
            'name' => 'Editorial Default',
            'slug' => 'editorial-default',
            'description' => 'The standard Superblog public layout.',
            'layout' => 'layouts.public',
            'is_active' => true,
        ]);
        $template->forceFill(['seed_key' => self::SEED_KEY])->save();
    }
}
