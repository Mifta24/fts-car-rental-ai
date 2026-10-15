@php
    $isEdit = $vehicle->exists;
@endphp

<x-admin-layout :title="$isEdit ? 'Edit vehicle' : 'Add vehicle'">
    <form method="POST" action="{{ $isEdit ? route('admin.vehicles.update', $vehicle) : route('admin.vehicles.store') }}" class="space-y-8">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Basics</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Name (Indonesian, default)</label>
                    <input type="text" name="name" value="{{ old('name', $vehicle->name) }}" required
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Description (Indonesian, default)</label>
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description', $vehicle->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Name (English)</label>
                    <input type="text" name="translations[en][name]" value="{{ old('translations.en.name', $vehicle->translations['en']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Name (Japanese)</label>
                    <input type="text" name="translations[ja][name]" value="{{ old('translations.ja.name', $vehicle->translations['ja']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Description (English)</label>
                    <textarea name="translations[en][description]" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('translations.en.description', $vehicle->translations['en']['description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Description (Japanese)</label>
                    <textarea name="translations[ja][description]" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('translations.ja.description', $vehicle->translations['ja']['description'] ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Specifications</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Body type</label>
                    <input type="text" name="body_type" value="{{ old('body_type', $vehicle->body_type) }}" placeholder="suv, mpv, sedan, hatchback…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Model year</label>
                    <input type="number" name="model_year" value="{{ old('model_year', $vehicle->model_year) }}" min="1980" max="2100" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Seats</label>
                    <input type="number" name="seats" value="{{ old('seats', $vehicle->seats) }}" required min="1" max="60" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Luggage (bags)</label>
                    <input type="number" name="luggage_capacity" value="{{ old('luggage_capacity', $vehicle->luggage_capacity) }}" required min="0" max="50" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Transmission</label>
                    <select name="transmission" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach (['automatic' => 'Automatic', 'manual' => 'Manual'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('transmission', $vehicle->transmission) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Fuel type</label>
                    <select name="fuel_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach (['petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('fuel_type', $vehicle->fuel_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Pricing & inclusions</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Base price / day</label>
                    <input type="number" step="1000" name="base_price" value="{{ old('base_price', $vehicle->base_price) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Driver price / day</label>
                    <input type="number" step="1000" name="driver_price" value="{{ old('driver_price', $vehicle->driver_price) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Sort order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $vehicle->sort_order) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="insurance_included" value="1" @checked(old('insurance_included', $vehicle->insurance_included)) class="rounded border-slate-300">
                    Basic insurance included
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="driver_available" value="1" @checked(old('driver_available', $vehicle->driver_available)) class="rounded border-slate-300">
                    Driver service available
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $vehicle->is_active)) class="rounded border-slate-300">
                    Published (visible on the website)
                </label>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Features (comma-separated)</label>
                <input type="text" name="features" value="{{ old('features', implode(', ', $vehicle->features ?? [])) }}" placeholder="bluetooth, gps, sunroof, child seat"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Photos</h2>

            @if ($isEdit && $vehicle->images->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($vehicle->images as $image)
                        <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-2">
                            <img src="{{ $image->image_source }}" alt="{{ $image->alt_text }}" class="h-14 w-20 rounded object-cover">
                            <div class="flex-1 text-xs text-slate-500">
                                <p class="truncate">{{ $image->image_url }}</p>
                                <p>Tags: {{ implode(', ', $image->tags ?? []) }}</p>
                            </div>
                            <label class="flex items-center gap-1 text-xs text-red-600">
                                <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" class="rounded border-slate-300">
                                Delete
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-4 text-xs text-slate-500">Add photos by URL (a CDN link, or a path such as /images/vehicles/your-car.webp). Tag them so the AI Agent can pick the right one — e.g. "interior, dashboard" or "exterior, trunk".</p>
            @for ($i = 0; $i < 3; $i++)
                <div class="mt-2 grid grid-cols-6 gap-2">
                    <input type="url" name="new_images[{{ $i }}][url]" placeholder="https://…" class="col-span-3 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][tags]" placeholder="interior, dashboard" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][alt]" placeholder="Alt text" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endfor
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.vehicles.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Save vehicle</button>
        </div>
    </form>
</x-admin-layout>
