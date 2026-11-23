<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTemplateRequest;
use App\Models\Template;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
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
            $this->duplicateNameError();
        }

        return to_route('admin.templates.index')->with('status', 'Template updated.');
    }

    private function duplicateNameError(): never
    {
        throw ValidationException::withMessages(['name' => 'That template name is already in use.']);
    }
}
