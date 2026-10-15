<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VehicleController extends Controller
{
    use ResolvesCurrentRental;

    public function index(Request $request): View
    {
        $rental = $this->currentRental($request);

        $vehicles = $rental->vehicles()->withCount('images')->orderBy('sort_order')->get();

        return view('admin.vehicles.index', compact('rental', 'vehicles'));
    }

    public function create(Request $request): View
    {
        $rental = $this->currentRental($request);

        return view('admin.vehicles.form', [
            'rental' => $rental,
            'vehicle' => new Vehicle(['seats' => 4, 'luggage_capacity' => 2, 'transmission' => 'automatic', 'fuel_type' => 'petrol', 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rental = $this->currentRental($request);
        $data = $this->validated($request);

        $vehicle = $rental->vehicles()->create($data);
        $this->syncImages($vehicle, $request);

        return redirect()->route('admin.vehicles.index')->with('status', "Vehicle \"{$vehicle->name}\" created.");
    }

    public function edit(Request $request, Vehicle $vehicle): View
    {
        $rental = $this->currentRental($request);
        abort_if($vehicle->rental_id !== $rental->id, 404);

        $vehicle->load('images');

        return view('admin.vehicles.form', compact('rental', 'vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($vehicle->rental_id !== $rental->id, 404);

        $vehicle->update($this->validated($request, $vehicle));
        $this->syncImages($vehicle, $request);

        return redirect()->route('admin.vehicles.index')->with('status', "Vehicle \"{$vehicle->name}\" updated.");
    }

    public function destroy(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($vehicle->rental_id !== $rental->id, 404);

        $vehicle->delete();

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle deleted.');
    }

    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'translations.en.name' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
            'translations.ja.name' => ['nullable', 'string'],
            'translations.ja.description' => ['nullable', 'string'],
            'model_year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'seats' => ['required', 'integer', 'min:1', 'max:60'],
            'luggage_capacity' => ['required', 'integer', 'min:0', 'max:50'],
            'transmission' => ['required', 'in:automatic,manual'],
            'fuel_type' => ['required', 'in:petrol,diesel,hybrid,electric'],
            'body_type' => ['nullable', 'string', 'max:50'],
            'insurance_included' => ['sometimes', 'boolean'],
            'driver_available' => ['sometimes', 'boolean'],
            'driver_price' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'features' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $features = collect(explode(',', (string) ($data['features'] ?? '')))
            ->map(fn ($a) => Str::of($a)->trim()->slug('_')->toString())
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $data['name'],
            'slug' => $vehicle?->slug ?? $this->uniqueSlug($request->user()->currentRental(), $data['name']),
            'description' => $data['description'] ?? null,
            'translations' => [
                'en' => ['name' => $data['translations']['en']['name'] ?? null, 'description' => $data['translations']['en']['description'] ?? null],
                'ja' => ['name' => $data['translations']['ja']['name'] ?? null, 'description' => $data['translations']['ja']['description'] ?? null],
            ],
            'model_year' => $data['model_year'] ?? null,
            'seats' => $data['seats'],
            'luggage_capacity' => $data['luggage_capacity'],
            'transmission' => $data['transmission'],
            'fuel_type' => $data['fuel_type'],
            'body_type' => $data['body_type'] ?? null,
            'insurance_included' => $request->boolean('insurance_included'),
            'driver_available' => $request->boolean('driver_available'),
            'driver_price' => $data['driver_price'] ?? null,
            'base_price' => $data['base_price'],
            'features' => $features,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug($rental, string $name): string
    {
        $base = Str::slug($name) ?: 'car';
        $slug = $base;
        $suffix = 1;

        while ($rental->vehicles()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function syncImages(Vehicle $vehicle, Request $request): void
    {
        $deleteIds = collect($request->input('delete_images', []))->map(fn ($id) => (int) $id);
        if ($deleteIds->isNotEmpty()) {
            $vehicle->images()->whereIn('id', $deleteIds)->delete();
        }

        $newImages = collect($request->input('new_images', []))
            ->filter(fn ($row) => filled($row['url'] ?? null));

        $sort = $vehicle->images()->max('sort_order') + 1;
        foreach ($newImages as $row) {
            $vehicle->images()->create([
                'image_url' => $row['url'],
                'alt_text' => $row['alt'] ?? $vehicle->name,
                'tags' => collect(explode(',', $row['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->values()->all(),
                'sort_order' => $sort++,
            ]);
        }
    }
}
