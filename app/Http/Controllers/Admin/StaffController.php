<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        $this->authorizeStaffManagement();

        return view('admin.staff.index', [
            'staff' => User::query()
                ->orderByRaw("CASE approval_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
                ->orderBy('name')
                ->paginate(25),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeStaffManagement();

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:approve,reject'],
        ]);

        if ($request->user()->is($user)) {
            return back()->withErrors(['staff' => 'You cannot change your own account status.']);
        }

        $user->approval_status = $validated['action'] === 'approve' ? 'approved' : 'rejected';
        $user->save();

        $message = $validated['action'] === 'approve'
            ? "{$user->name} has been approved."
            : "{$user->name} has been rejected.";

        return to_route('admin.staff.index')->with('status', $message);
    }

    private function authorizeStaffManagement(): void
    {
        Gate::authorize('manage-staff');
    }
}
