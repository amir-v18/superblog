<?php

namespace App\Support;

use App\Models\Superblog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuperblogPublishingReadiness
{
    private const DOMAIN_PATTERN = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    public function errorsFor(Superblog $superblog): array
    {
        return Validator::make([
            'name' => $superblog->name,
            'slug' => $superblog->slug,
            'template_id' => $superblog->template_id,
            'domain' => $superblog->domain,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'template_id' => ['required', 'integer', Rule::exists('templates', 'id')],
            'domain' => ['required', 'string', 'max:253', 'regex:'.self::DOMAIN_PATTERN],
        ], [
            'name.required' => 'Ange ett namn under Grund innan du publicerar.',
            'slug.required' => 'Ange en slug under Grund innan du publicerar.',
            'slug.regex' => 'Ange en giltig slug under Grund innan du publicerar.',
            'template_id.required' => 'Välj en mall under Grund innan du publicerar.',
            'template_id.exists' => 'Välj en giltig mall under Grund innan du publicerar.',
            'domain.required' => 'Ange en domän under Grund innan du publicerar.',
            'domain.regex' => 'Ange en giltig domän, till exempel teknikbyn.se. DNS-kontroll görs inte ännu.',
        ])->errors()->messages();
    }

    public function validate(Superblog $superblog): void
    {
        $errors = $this->errorsFor($superblog);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
