<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class AdoptInitialTemplate extends Command
{
    private const SEED_KEY = 'initial-editorial';

    protected $signature = 'superblog:adopt-initial-template {template_id : Database ID of the intended legacy template}';

    protected $description = 'Assign the initial template seed key to a chosen existing template';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Template adoption is allowed only in local or testing environments.');

            return self::FAILURE;
        }

        $templateId = filter_var($this->argument('template_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($templateId === false) {
            $this->error('Provide a valid positive template database ID.');

            return self::FAILURE;
        }

        $template = Template::find($templateId);

        if (! $template) {
            $this->error("No template exists with ID {$templateId}.");

            return self::FAILURE;
        }

        if ($template->seed_key === self::SEED_KEY) {
            $this->info("Template ID {$templateId} already has the initial template key.");

            return self::SUCCESS;
        }

        if ($template->seed_key !== null) {
            $this->error("Template ID {$templateId} already has a different seed key; no changes were made.");

            return self::FAILURE;
        }

        if (Template::where('seed_key', self::SEED_KEY)->exists()) {
            $this->error('The initial template key is already assigned to another template; no changes were made.');

            return self::FAILURE;
        }

        $this->table(['ID', 'Name', 'Slug', 'Layout'], [[
            $template->getKey(), $template->name, $template->slug, $template->layout,
        ]]);

        if (! $this->confirm("Assign initial-editorial to template ID {$templateId}?")) {
            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($templateId): string {
                $template = Template::whereKey($templateId)->lockForUpdate()->first();

                if (! $template) {
                    return 'missing';
                }

                if ($template->seed_key !== null && $template->seed_key !== self::SEED_KEY) {
                    return 'template_key_conflict';
                }

                $keyOwner = Template::where('seed_key', self::SEED_KEY)->first();

                if ($keyOwner && $keyOwner->getKey() !== $template->getKey()) {
                    return 'key_conflict';
                }

                if ($template->seed_key === self::SEED_KEY) {
                    return 'already_adopted';
                }

                $template->forceFill(['seed_key' => self::SEED_KEY])->save();

                return 'adopted';
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('The initial template key was assigned concurrently; no changes were made.');

            return self::FAILURE;
        }

        if ($result === 'adopted' || $result === 'already_adopted') {
            $this->info("Template ID {$templateId} is assigned the initial template key.");

            return self::SUCCESS;
        }

        $message = match ($result) {
            'missing' => "Template ID {$templateId} no longer exists; no changes were made.",
            'template_key_conflict' => "Template ID {$templateId} now has a different seed key; no changes were made.",
            default => 'The initial template key is already assigned to another template; no changes were made.',
        };

        $this->error($message);

        return self::FAILURE;
    }
}
