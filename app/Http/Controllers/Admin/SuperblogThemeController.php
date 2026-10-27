<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSuperblogThemeRequest;
use App\Models\Superblog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SuperblogThemeController extends Controller
{
    public function __invoke(SaveSuperblogThemeRequest $request, Superblog $superblog): RedirectResponse
    {
        $name = $request->validated('theme');

        // Read-modify-write under a row lock, so concurrent saves cannot drop each other's settings keys.
        DB::transaction(function () use ($superblog, $name): void {
            $locked = Superblog::whereKey($superblog->getKey())->lockForUpdate()->firstOrFail();
            $settings = $locked->settings ?? [];
            $theme = is_array($settings['theme'] ?? null) ? $settings['theme'] : [];
            // Only the name changes; other theme keys (e.g. future color overrides) are kept.
            $settings['theme'] = ['name' => $name] + $theme;
            $locked->settings = $settings;
            $locked->save();
        });

        return to_route('admin.superblogs.edit', [$superblog, 'flik' => 'utseende'])->with('status', 'Utseendet har sparats.');
    }
}
