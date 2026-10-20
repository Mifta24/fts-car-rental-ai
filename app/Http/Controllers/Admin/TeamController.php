<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\RentalUser;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    use ResolvesCurrentRental;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);

        return view('admin.team.index', [
            'rental' => $rental,
            'members' => $rental->users()->orderBy('name')->get(),
            'roles' => RentalUser::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rental = $this->currentRental($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::in(RentalUser::ROLES)],
        ], ['email.exists' => __('admin.team_unknown_email')]);

        $member = User::where('email', $data['email'])->firstOrFail();

        if ($rental->users()->whereKey($member->id)->exists()) {
            return back()->withErrors(['email' => __('admin.team_already_member')])->withInput();
        }

        $rental->users()->attach($member->id, ['role' => $data['role'], 'status' => 'active']);
        $this->audit->record($rental, $request->user(), 'team.member_added', $member, ['role' => [null, $data['role']]], $member->email);

        return back()->with('status', __('admin.team_member_added', ['name' => $member->name]));
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $rental = $this->currentRental($request);
        $current = $rental->users()->whereKey($member->id)->first();
        abort_if($current === null, 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(RentalUser::ROLES)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $wasActiveOwner = $current->pivot->role === RentalUser::ROLE_OWNER && $current->pivot->status === 'active';
        $staysActiveOwner = $data['role'] === RentalUser::ROLE_OWNER && $data['status'] === 'active';

        if ($wasActiveOwner && ! $staysActiveOwner && $this->activeOwnerCount($rental) <= 1) {
            return back()->with('error', __('admin.team_last_owner'));
        }

        $changes = array_filter([
            'role' => $current->pivot->role === $data['role'] ? null : [$current->pivot->role, $data['role']],
            'status' => $current->pivot->status === $data['status'] ? null : [$current->pivot->status, $data['status']],
        ]);

        if ($changes === []) {
            return back();
        }

        $rental->users()->updateExistingPivot($member->id, $data);
        $this->audit->record($rental, $request->user(), 'team.member_updated', $member, $changes, $member->email);

        return back()->with('status', __('admin.team_member_updated', ['name' => $member->name]));
    }

    private function activeOwnerCount($rental): int
    {
        return $rental->users()
            ->wherePivot('role', RentalUser::ROLE_OWNER)
            ->wherePivot('status', 'active')
            ->count();
    }
}
