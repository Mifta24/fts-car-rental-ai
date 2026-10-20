<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\RentalAddon;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AddonController extends Controller
{
    use ResolvesCurrentRental;

    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request): RedirectResponse
    {
        $rental = $this->currentRental($request);

        $addon = $rental->addons()->create($this->validated($request));
        $this->audit->record($rental, $request->user(), 'pricing.addon_created', $addon, ['price' => [null, $addon->price]], $addon->name);

        return back()->with('status', __('admin.pricing_saved'));
    }

    public function update(Request $request, RentalAddon $addon): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($addon->rental_id !== $rental->id, 404);

        $addon->fill($this->validated($request));
        $changes = $this->audit->dirtyChanges($addon);
        $addon->save();
        $this->audit->record($rental, $request->user(), 'pricing.addon_updated', $addon, $changes, $addon->name);

        return back()->with('status', __('admin.pricing_saved'));
    }

    public function destroy(Request $request, RentalAddon $addon): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($addon->rental_id !== $rental->id, 404);

        $addon->delete();
        $this->audit->record($rental, $request->user(), 'pricing.addon_deleted', $addon, [], $addon->name);

        return back()->with('status', __('admin.pricing_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'translations.en.name' => ['nullable', 'string', 'max:120'],
            'translations.ja.name' => ['nullable', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'charge' => ['required', Rule::in(RentalAddon::CHARGES)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'name' => $data['name'],
            'translations' => [
                'en' => ['name' => $data['translations']['en']['name'] ?? null],
                'ja' => ['name' => $data['translations']['ja']['name'] ?? null],
            ],
            'price' => $data['price'],
            'charge' => $data['charge'],
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }
}
