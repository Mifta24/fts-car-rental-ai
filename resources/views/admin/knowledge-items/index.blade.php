<x-admin-layout title="Knowledge base">
    <x-slot name="actions">
        <a href="{{ route('admin.knowledge-items.create') }}" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Add entry
        </a>
    </x-slot>

    <p class="mb-4 text-sm text-slate-500">This is the only source the AI Rental Agent is allowed to answer rental-fact questions from. If it's not here, the AI won't guess.</p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    <tr>
                        <td class="px-4 py-3 text-slate-500">{{ ucfirst($item->category) }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $item->title }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $item->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $item->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.knowledge-items.edit', $item) }}" class="text-slate-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.knowledge-items.destroy', $item) }}" class="inline" onsubmit="return confirm('Delete this entry?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No knowledge base entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
