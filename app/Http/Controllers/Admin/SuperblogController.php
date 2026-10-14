<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSuperblogRequest;
use App\Models\Superblog;
use App\Models\Template;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SuperblogController extends Controller
{
    public function create(): View
    {
        Gate::authorize('create', Superblog::class);

        return view('admin.superblogs.form', [
            'superblog' => new Superblog,
            'templates' => Template::orderBy('name')->get(),
        ]);
    }

    public function store(SaveSuperblogRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request) {
                $superblog = $request->user()->superblogs()->create($request->validated());
                // Ownership alone does not grant access: the policy uses staff assignments.
                $superblog->staff()->attach($request->user()->getKey());
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->duplicateError();
        }

        return to_route('admin.dashboard')->with('status', 'Superblog created.');
    }

    public function edit(Superblog $superblog): View
    {
        Gate::authorize('view', $superblog);
        Gate::authorize('update', $superblog);

        return view('admin.superblogs.form', [
            'superblog' => $superblog,
            'templates' => Template::orderBy('name')->get(),
        ]);
    }

    public function update(SaveSuperblogRequest $request, Superblog $superblog): RedirectResponse
    {
        try {
            $superblog->update($request->validated());
        } catch (UniqueConstraintViolationException $exception) {
            $this->duplicateError();
        }

        return to_route('admin.dashboard')->with('status', 'Superblog updated.');
    }

    public function confirmDeletion(Superblog $superblog): View
    {
        Gate::authorize('view', $superblog);
        Gate::authorize('delete', $superblog);

        return view('admin.superblogs.delete', compact('superblog'));
    }

    public function destroy(Superblog $superblog): RedirectResponse
    {
        Gate::authorize('delete', $superblog);
        $superblog->delete();

        return to_route('admin.dashboard')->with('status', 'Superblog deleted.');
    }

    private function duplicateError(): never
    {
        // Unique database constraints also protect against concurrent form submissions.
        throw ValidationException::withMessages(['slug' => 'The slug or domain is already in use. Please choose another.']);
    }
}
