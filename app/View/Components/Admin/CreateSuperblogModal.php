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
        return view('components.admin.create-superblog-modal', [
            // Same list as SuperblogController::create() offers for a new Superblog.
            'templates' => Template::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
