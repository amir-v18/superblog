<?php

namespace App\Http\Controllers;

use App\Models\Superblog;
use App\Support\SuperblogHomeContent;
use Illuminate\View\View;

class PublicSuperblogController extends Controller
{
    public function __invoke(Superblog $superblog, SuperblogHomeContent $homeContent): View
    {
        abort_unless($superblog->status === 'published', 404);

        // The same site as the builder's preview, so the published page looks like what was previewed.
        return view($homeContent->siteFor($superblog).'.home', [
            'superblog' => $superblog,
            ...$homeContent->for($superblog),
            'isPreview' => false,
        ]);
    }
}
