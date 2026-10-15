<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'rental_id',
    'name',
    'slug',
    'description',
    'translations',
    'model_year',
    'seats',
    'luggage_capacity',
    'transmission',
    'fuel_type',
    'body_type',
    'insurance_included',
    'driver_available',
    'driver_price',
    'base_price',
    'features',
    'is_active',
    'sort_order',
])]
class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'features' => 'array',
            'insurance_included' => 'boolean',
            'driver_available' => 'boolean',
            'driver_price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(VehicleImage::class)->orderBy('sort_order');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(VehicleInventory::class);
    }

    public function maxPassengers(): int
    {
        return $this->seats;
    }

    public function translatedName(string $locale): string
    {
        $value = $this->translations[$locale]['name'] ?? null;

        return filled($value) ? $value : $this->name;
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }
}
