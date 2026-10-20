<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'custom_domain',
    'description',
    'translations',
    'address',
    'city',
    'country',
    'latitude',
    'longitude',
    'phone',
    'whatsapp',
    'email',
    'timezone',
    'currency',
    'default_locale',
    'open_time',
    'close_time',
    'logo_path',
    'cover_path',
    'public_status',
])]
class Rental extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rental_users')
            ->using(RentalUser::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function knowledgeItems(): HasMany
    {
        return $this->hasMany(RentalKnowledgeItem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(RentalAddon::class)->orderBy('sort_order')->orderBy('id');
    }

    public function promoCodes(): HasMany
    {
        return $this->hasMany(PromoCode::class);
    }

    public function durationDiscounts(): HasMany
    {
        return $this->hasMany(DurationDiscount::class)->orderBy('min_days');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function translatedDescription(string $locale): ?string
    {
        $value = $this->translations[$locale]['description'] ?? null;

        return filled($value) ? $value : $this->description;
    }

    public function isPublished(): bool
    {
        return $this->public_status === 'published';
    }

    public function publicUrl(): string
    {
        return $this->custom_domain
            ? 'https://'.$this->custom_domain
            : url('/'.$this->slug);
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'rental';
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, static::reservedSlugs(), true)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function reservedSlugs(): array
    {
        return [
            'admin', 'login', 'logout', 'register', 'pricing', 'dashboard',
            'api', 'terms', 'privacy', 'forgot-password', 'reset-password',
            'verify-email', 'confirm-password', 'storage', 'build', 'account',
        ];
    }
}
