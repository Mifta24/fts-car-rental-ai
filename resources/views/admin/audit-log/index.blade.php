<x-admin-layout title="Audit log">
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (array_merge([''], $areas) as $value)
            <a href="{{ route('admin.audit-log.index', $value ? ['area' => $value] : []) }}"
                class="rounded-full px-3 py-1 {{ ($area ?: '') === $value ? 'bg-slate-900 text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
                {{ $value === '' ? 'All' : ucfirst(str_replace('_', ' ', $value)) }}
            </a>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Who</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Changes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $log->created_at->timezone($rental->timezone)->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->subject_label }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            @foreach ($log->changes ?? [] as $field => $change)
                                <p>
                                    <span class="font-medium">{{ $field }}</span>:
                                    @if (is_array($change) && array_is_list($change) && count($change) === 2)
                                        {{ is_array($change[0]) ? json_encode($change[0]) : ($change[0] ?? '—') }} → {{ is_array($change[1]) ? json_encode($change[1]) : ($change[1] ?? '—') }}
                                    @else
                                        {{ is_array($change) ? json_encode($change) : $change }}
                                    @endif
                                </p>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</x-admin-layout>
