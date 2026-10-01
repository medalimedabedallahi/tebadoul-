<?php

namespace App\Models;

use App\Enums\AdPlacement;
use Database\Factories\AdvertisementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Advertisement managed by administrators and shown in one placement of the web pages.
 *
 * The banner bytes live in `image_data`; queries that only list or show advertisements select
 * {@see self::SUMMARY_COLUMNS} so the image is read only by the route that serves it.
 *
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string|null $link_url
 * @property AdPlacement $placement
 * @property string|null $locale
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property mixed $image_data
 * @property string $image_mime_type
 * @property string $image_checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'link_url', 'placement', 'locale', 'is_active', 'starts_at', 'ends_at'])]
#[RouteKey('public_id')]
class Advertisement extends Model
{
    /** @use HasFactory<AdvertisementFactory> */
    use HasFactory, HasUlids;

    /**
     * Every column except the image bytes.
     *
     * @var list<string>
     */
    public const SUMMARY_COLUMNS = [
        'id', 'public_id', 'title', 'link_url', 'placement', 'locale', 'is_active',
        'starts_at', 'ends_at', 'image_mime_type', 'image_checksum', 'created_at', 'updated_at',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'placement' => AdPlacement::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Active advertisements whose period, when set, includes the given moment.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDisplayable(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();

        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    public function isDisplayable(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte($at))
            && ($this->ends_at === null || $this->ends_at->gt($at));
    }

    /**
     * URL of the banner; the checksum changes with the image, so the response can be cached for good.
     */
    public function imageUrl(): string
    {
        return route('advertisements.image', ['advertisement' => $this->public_id, 'v' => substr($this->image_checksum, 0, 16)]);
    }

    /**
     * The banner bytes. PostgreSQL returns a bytea column as a stream, SQLite as a string.
     */
    public function imageBytes(): string
    {
        $data = $this->image_data;

        return is_resource($data) ? (string) stream_get_contents($data) : (string) $data;
    }
}
