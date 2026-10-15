<x-admin-layout title="Handovers">
    <div class="mb-4 flex gap-2 text-sm">
        @foreach (['open' => 'Open', 'resolved' => 'Resolved'] as $value => $label)
            <a href="{{ route('admin.handovers.index', ['status' => $value]) }}"
                class="rounded-full px-3 py-1 {{ $status === $value ? 'bg-slate-900 text-white' : 'bg-white border border-slate-300 text-slate-600' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($handovers as $handover)
            <a href="{{ route('admin.handovers.show', $handover) }}" class="block rounded-xl border border-slate-200 bg-white p-4 hover:border-slate-300">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-slate-900">{{ ucwords(str_replace('_', ' ', $handover->reason)) }}</p>
                    <p class="text-xs text-slate-400">{{ $handover->created_at->diffForHumans() }}</p>
                </div>
                <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $handover->summary }}</p>
            </a>
        @empty
            <p class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-slate-400">Nothing here.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $handovers->links() }}</div>
</x-admin-layout>
