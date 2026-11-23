<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Assign the approved stable keys to the existing local catalog rows by database ID.
     * Editable names and slugs are deliberately not consulted.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $templates = DB::table('templates')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'seed_key']);

            // A fresh database has no legacy rows; TemplateSeeder will create them with keys.
            if ($templates->isEmpty()) {
                return;
            }

            $mapping = [
                3 => 'template-modern',
                4 => 'template-classic',
            ];

            foreach ($mapping as $templateId => $seedKey) {
                $template = $templates->firstWhere('id', $templateId);

                if ($template === null) {
                    throw new RuntimeException("Cannot safely assign {$seedKey}: expected template ID {$templateId} is missing.");
                }

                if ($template->seed_key !== null && $template->seed_key !== $seedKey) {
                    throw new RuntimeException("Cannot safely assign {$seedKey}: template ID {$templateId} already has a different key.");
                }

                $keyOwner = $templates->firstWhere('seed_key', $seedKey);

                if ($keyOwner !== null && (int) $keyOwner->id !== $templateId) {
                    throw new RuntimeException("Cannot safely assign {$seedKey}: the key already belongs to template ID {$keyOwner->id}.");
                }
            }

            $unmappedIds = $templates
                ->whereNull('seed_key')
                ->reject(fn (object $template): bool => array_key_exists((int) $template->id, $mapping))
                ->pluck('id')
                ->implode(', ');

            if ($unmappedIds !== '') {
                throw new RuntimeException('Cannot safely assign stable template keys. Explicitly map these template IDs first: '.$unmappedIds);
            }

            foreach ($mapping as $templateId => $seedKey) {
                DB::table('templates')
                    ->where('id', $templateId)
                    ->whereNull('seed_key')
                    ->update(['seed_key' => $seedKey]);
            }
        });
    }

    public function down(): void
    {
        // Keep the assigned stable identities on rollback; removing identity data could make a later
        // deployment seed duplicate templates or render existing Superblogs unpredictably.
    }
};
