<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Superblog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $this->authorizeStaffManagement();

        return view('admin.staff.index', [
            'staff' => User::query()
                ->with('assignedSuperblogs')
                ->withCount('superblogs')
                ->orderByRaw("CASE approval_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderBy('name')
                ->paginate(25),
            'superblogs' => Superblog::query()->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeStaffManagement();

        $rules = ['action' => ['required', 'string', 'in:approve,reject,assign']];

        if ($request->input('action') === 'assign') {
            $rules['assignments'] = ['sometimes', 'array'];
            $rules['assignments.*'] = ['integer', 'distinct', 'exists:superblogs,id'];
        }

        $validated = $request->validate($rules);

        if ($validated['action'] === 'assign') {
            if ($user->is_super_admin || $user->has_all_superblogs_access) {
                throw ValidationException::withMessages([
                    'assignments' => 'Assignment editing is only available for ordinary staff accounts.',
                ]);
            }

            $assignmentIds = $validated['assignments'] ?? [];
            $staffName = DB::transaction(function () use ($user, $assignmentIds): string {
                $staff = User::query()->lockForUpdate()->findOrFail($user->getKey());

                // Recheck role flags while locked so assignment edits never alter broader access.
                if ($staff->is_super_admin || $staff->has_all_superblogs_access) {
                    throw ValidationException::withMessages([
                        'assignments' => 'Assignment editing is only available for ordinary staff accounts.',
                    ]);
                }

                $staff->assignedSuperblogs()->sync($assignmentIds);

                return $staff->name;
            });

            return to_route('admin.staff.index')->with('status', "Superblog assignments updated for {$staffName}.");
        }

        if ($request->user()->is($user)) {
            return back()->withErrors(['staff' => 'You cannot change your own account status.']);
        }

        if ($user->is_super_admin) {
            return back()->withErrors(['staff' => 'You cannot change the approval status of a super-admin account here.']);
        }

        $user->approval_status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $user->save();

        $message = $validated['action'] === 'approve'
            ? "{$user->name} has been approved."
            : "{$user->name} has been rejected.";

        return to_route('admin.staff.index')->with('status', $message);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeStaffManagement();

        $name = DB::transaction(function () use ($request, $user): string {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->ensureAccountCanBeDeleted($request, $target);

            $target->assignedSuperblogs()->detach();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $target->getKey())
                    ->delete();
            }

            DB::table('password_reset_tokens')->where('email', $target->email)->delete();

            $name = $target->name;
            $target->delete();

            return $name;
        });

        return to_route('admin.staff.index')->with('status', "Permanently deleted the account for {$name}.");
    }

    public function confirmDeletion(Request $request, User $user): View
    {
        $this->authorizeStaffManagement();
        $this->ensureAccountCanBeDeleted($request, $user);

        return view('admin.staff.delete', ['user' => $user]);
    }

    private function ensureAccountCanBeDeleted(Request $request, User $user): void
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'staff' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->is_super_admin) {
            throw ValidationException::withMessages([
                'staff' => 'Super-admin accounts cannot be deleted from Staff Management.',
            ]);
        }

        if ($user->superblogs()->exists()) {
            throw ValidationException::withMessages([
                'staff' => 'This account owns one or more Superblogs. Transfer ownership explicitly before deleting it.',
            ]);
        }
    }

    private function authorizeStaffManagement(): void
    {
        Gate::authorize('manage-staff');
    }
}
