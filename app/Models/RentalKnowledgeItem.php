<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The approved knowledge base the AI Rental Agent retrieves from. This is the
 * ONLY place rental facts may come from — the model is never allowed to
 * answer a rental-knowledge question from its own memory.
 */
#[Fillable([
    'rental_id',
    'category',
    'title',
    'body',
    'translations',
    'tags',
    'image_url',
    'is_active',
    'sort_order',
])]
class RentalKnowledgeItem extends Model
{
    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_SERVICES = 'services';

    public const CATEGORY_POLICIES = 'policies';

    public const CATEGORY_PACKAGES = 'packages';

    public const CATEGORY_DELIVERY = 'delivery';

    public const CATEGORY_FAQ = 'faq';

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * A short icon name for this entry, taken from its tags or title so the
     * services cards can show a matching symbol without another column.
     */
    public function iconName(): string
    {
        $haystack = Str::lower($this->title.' '.implode(' ', $this->tags ?? []));

        foreach ([
            'driver' => ['driver', 'sopir', 'supir', 'chauffeur'],
            'delivery' => ['airport', 'bandara', 'delivery', 'antar', 'jemput', 'pickup', 'pick-up', 'one-way'],
            'insurance' => ['insurance', 'asuransi', 'protection', 'proteksi', 'damage', 'kerusakan', 'deposit'],
            'gps' => ['gps', 'navigation', 'navigasi', 'dashcam'],
            'child' => ['child', 'baby', 'anak', 'kursi bayi'],
            'package' => ['package', 'paket', 'wedding', 'tour', 'wisata', 'weekly', 'monthly', 'mingguan', 'bulanan'],
            'place' => ['route', 'rute', 'destination', 'tujuan', 'attraction', 'atraksi', 'toll', 'tol'],
            'support' => ['support', 'breakdown', 'derek', 'emergency', 'darurat', 'roadside'],
        ] as $icon => $needles) {
            foreach ($needles as $needle) {
                if (Str::contains($haystack, $needle)) {
                    return $icon;
                }
            }
        }

        return 'star';
    }

    public function translatedTitle(string $locale): string
    {
        $value = $this->translations[$locale]['title'] ?? null;

        return filled($value) ? $value : $this->title;
    }

    public function translatedBody(string $locale): string
    {
        $value = $this->translations[$locale]['body'] ?? null;

        return filled($value) ? $value : $this->body;
    }
}
