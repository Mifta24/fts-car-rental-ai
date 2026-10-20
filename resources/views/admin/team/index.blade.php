<x-admin-layout title="Team">
    <p class="mb-4 max-w-2xl text-sm text-slate-500">
        <strong>Owner</strong> can do everything including managing the team. <strong>Staff</strong> can manage vehicles, bookings and handovers.
        <strong>Viewer</strong> can only look.
    </p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Member</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($members as $member)
                    <tr>
                        <form method="POST" action="{{ route('admin.team.update', $member) }}">
                            @csrf @method('PATCH')
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ $member->name }}</p>
                                <p class="text-xs text-slate-500">{{ $member->email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <select name="role" aria-label="Role of {{ $member->name }}" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected($member->pivot->role === $role)>{{ ucfirst($role) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <select name="status" aria-label="Status of {{ $member->name }}" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                                    @foreach (['active', 'inactive'] as $status)
                                        <option value="{{ $status }}" @selected($member->pivot->status === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="submit" class="text-slate-600 hover:underline">Save</button>
                            </td>
                        </form>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('admin.team.store') }}" class="mt-8 max-w-xl rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <h2 class="mb-3 text-lg font-medium text-slate-900">Add an existing user</h2>
        <div class="flex flex-wrap items-start gap-3">
            <div class="min-w-48 flex-1">
                <label for="member-email" class="mb-1 block text-xs text-slate-500">Account email</label>
                <input id="member-email" type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">
                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="member-role" class="mb-1 block text-xs text-slate-500">Role</label>
                <select id="member-role" name="role" class="rounded-md border border-slate-300 px-2 py-1 text-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', 'staff') === $role)>{{ ucfirst($role) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="mt-5 rounded-full bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Add</button>
        </div>
    </form>
</x-admin-layout>
