<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Superblog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Superblog::class);

        return view('admin.dashboard', [
            'superblogs' => Superblog::accessibleTo($request->user())->with('template')->orderBy('name')->orderBy('id')->paginate(20),
        ]);
    }
}
