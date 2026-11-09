<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Superblog;
use App\Support\SuperblogHomeContent;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SuperblogPreviewController extends Controller
{
    /**
     * The builder's live preview, shown in an iframe. Same access as the builder itself.
     */
    public function __invoke(Superblog $superblog, SuperblogHomeContent $homeContent): Response
    {
        Gate::authorize('view', $superblog);
        Gate::authorize('update', $superblog);

        // The header also covers non-HTML crawlers; the meta tag in the template is a second line of defence.
        return response()
            ->view($homeContent->siteFor($superblog).'.home', [
                'superblog' => $superblog,
                ...$homeContent->for($superblog),
                'isPreview' => true,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
