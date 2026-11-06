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

        return view('templates.news.home', [
            'superblog' => $superblog,
            ...$homeContent->for($superblog),
            'isPreview' => false,
        ]);
    }
}
