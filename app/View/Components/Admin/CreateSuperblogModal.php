<?php

namespace App\View\Components\Admin;

use App\Models\Template;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The "Nytt magasin" modal. It sits in the admin layout so the sidebar link opens it on every admin page,
 * and it loads its own templates so no controller has to pass them. The layout only renders it for
 * users the SuperblogPolicy lets create, so everyone else gets neither the modal nor the query.
 */
class CreateSuperblogModal extends Component
{
    public function render(): View
    {
        // Position of each slug in config/templates.php. Templates missing there go last.
        $order = array_flip(array_keys(config('templates.list', [])));

        // Same templates as SuperblogController::create() offers, but in the registry's order. The sort is
        // stable, so templates missing from the registry keep their name order among themselves.
        $templates = Template::where('is_active', true)->orderBy('name')->get()
            ->sortBy(fn (Template $template) => $order[$template->slug] ?? count($order))
            ->values();

        // The registry's default is preselected; if it is missing or inactive the first card is, so a choice is always made.
        $default = $templates->firstWhere('slug', config('templates.default')) ?? $templates->first();

        return view('components.admin.create-superblog-modal', [
            'templates' => $templates,
            'defaultTemplate' => $default?->id,
        ]);
    }
}
