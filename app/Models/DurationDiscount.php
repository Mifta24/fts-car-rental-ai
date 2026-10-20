<?php

namespace App\Models;

use Database\Factories\DurationDiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Rent for N days or more and get a percentage off the rental price."
 */
#[Fillable(['rental_id', 'min_days', 'percent'])]
class DurationDiscount extends Model
{
    /** @use HasFactory<DurationDiscountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['percent' => 'decimal:2'];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
