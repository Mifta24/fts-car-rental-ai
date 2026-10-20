<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentRental;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\Rental;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromoCodeController extends Controller
{
    use ResolvesCurrentRental;

    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request): RedirectResponse
    {
        $rental = $this->currentRental($request);

        $promo = $rental->promoCodes()->create($this->validated($request, $rental));
        $this->audit->record($rental, $request->user(), 'pricing.promo_created', $promo, ['discount_value' => [null, $promo->discount_value]], $promo->code);

        return back()->with('status', __('admin.pricing_saved'));
    }

    public function update(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($promoCode->rental_id !== $rental->id, 404);

        $promoCode->fill($this->validated($request, $rental, $promoCode));
        $changes = $this->audit->dirtyChanges($promoCode);
        $promoCode->save();
        $this->audit->record($rental, $request->user(), 'pricing.promo_updated', $promoCode, $changes, $promoCode->code);

        return back()->with('status', __('admin.pricing_saved'));
    }

    public function destroy(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $rental = $this->currentRental($request);
        abort_if($promoCode->rental_id !== $rental->id, 404);

        $promoCode->delete();
        $this->audit->record($rental, $request->user(), 'pricing.promo_deleted', $promoCode, [], $promoCode->code);

        return back()->with('status', __('admin.pricing_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Rental $rental, ?PromoCode $promoCode = null): array
    {
        $request->merge(['code' => PromoCode::normalize((string) $request->input('code'))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('promo_codes', 'code')->where('rental_id', $rental->id)->ignore($promoCode?->id)],
            'discount_type' => ['required', Rule::in(PromoCode::TYPES)],
            'discount_value' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'min_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($data['discount_type'] === PromoCode::TYPE_PERCENT && $data['discount_value'] > 100) {
            throw ValidationException::withMessages(['discount_value' => __('admin.promo_percent_max')]);
        }

        return [
            'code' => $data['code'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'min_days' => $data['min_days'] ?? 1,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
