<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTemplateRequest;
use App\Models\Template;
use Illuminate\Database\ForeignKeyConstraintViolationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Template::class);

        return view('admin.templates.index', [
            'templates' => Template::withCount('superblogs')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Template::class);

        return view('admin.templates.form', ['template' => new Template]);
    }

    public function store(SaveTemplateRequest $request): RedirectResponse
    {
        try {
            Template::create($request->validated());
        } catch (UniqueConstraintViolationException) {
            $this->duplicateSlugError();
        }

        return to_route('admin.templates.index')->with('status', 'Template created.');
    }

    public function edit(Template $template): View
    {
        Gate::authorize('update', $template);

        return view('admin.templates.form', compact('template'));
    }

    public function update(SaveTemplateRequest $request, Template $template): RedirectResponse
    {
        try {
            $template->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            $this->duplicateSlugError();
        }

        return to_route('admin.templates.index')->with('status', 'Template updated.');
    }

    public function confirmDeletion(Template $template): View
    {
        Gate::authorize('delete', $template);

        return view('admin.templates.delete', [
            'template' => $template,
            'inUse' => $template->superblogs()->exists(),
        ]);
    }

    public function destroy(Template $template): RedirectResponse
    {
        Gate::authorize('delete', $template);

        try {
            $deleted = DB::transaction(function () use ($template): bool {
                $lockedTemplate = Template::whereKey($template->getKey())->lockForUpdate()->firstOrFail();

                if ($lockedTemplate->superblogs()->exists()) {
                    return false;
                }

                $lockedTemplate->delete();

                return true;
            });
        } catch (ForeignKeyConstraintViolationException) {
            return to_route('admin.templates.index')->withErrors([
                'template' => 'This template is in use and cannot be deleted.',
            ]);
        }

        if (! $deleted) {
            return to_route('admin.templates.index')->withErrors([
                'template' => 'This template is in use and cannot be deleted.',
            ]);
        }

        return to_route('admin.templates.index')->with('status', 'Template deleted.');
    }

    private function duplicateSlugError(): never
    {
        throw ValidationException::withMessages(['slug' => 'That template slug is already in use.']);
    }
}
