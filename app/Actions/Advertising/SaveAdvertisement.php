<?php

namespace App\Actions\Advertising;

use App\Enums\AdPlacement;
use App\Models\Advertisement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

/**
 * Creates an advertisement, or updates one when a public id is given; on update the banner is
 * replaced only when a new image is sent.
 *
 * The image is limited to raster formats (no SVG, which could carry script) and stored in the row,
 * with its detected MIME type and a SHA-256 checksum used to cache-bust its URL.
 */
final class SaveAdvertisement
{
    public const MAX_IMAGE_KILOBYTES = 2048;

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $creating = true): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'link_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'placement' => ['required', Rule::enum(AdPlacement::class)],
            'locale' => ['nullable', Rule::in(config('app.supported_locales', ['fr', 'ar']))],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'image' => [
                $creating ? 'required' : 'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(self::MAX_IMAGE_KILOBYTES),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input, ?string $publicId = null): Advertisement
    {
        if ($publicId === null) {
            Gate::forUser($actor)->authorize('create', Advertisement::class);
        }

        foreach (['title', 'link_url'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }

        $validated = Validator::make($input, self::rules($publicId === null), attributes: $this->attributeNames())->validate();
        $image = $validated['image'] ?? null;
        $imageMimeType = $image instanceof UploadedFile ? $this->detectImageMimeType($image) : null;

        if ($image instanceof UploadedFile && $imageMimeType === null) {
            throw ValidationException::withMessages([
                'image' => __('validation.image', ['attribute' => $this->attributeNames()['image']]),
            ]);
        }

        $startsAt = filled($validated['starts_at'] ?? null) ? Carbon::parse($validated['starts_at']) : null;
        $endsAt = filled($validated['ends_at'] ?? null) ? Carbon::parse($validated['ends_at']) : null;

        if ($startsAt !== null && $endsAt !== null && ! $endsAt->isAfter($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => __('validation.after', ['attribute' => $this->attributeNames()['ends_at'], 'date' => $this->attributeNames()['starts_at']]),
            ]);
        }

        return DB::transaction(function () use ($actor, $validated, $publicId, $startsAt, $endsAt, $image, $imageMimeType): Advertisement {
            if ($publicId === null) {
                $advertisement = new Advertisement;
            } else {
                $advertisement = Advertisement::query()
                    ->select(Advertisement::SUMMARY_COLUMNS)
                    ->where('public_id', Str::lower($publicId))
                    ->lockForUpdate()
                    ->firstOrFail();

                Gate::forUser($actor)->authorize('update', $advertisement);
            }

            $advertisement->fill([
                'title' => trim($validated['title']),
                'link_url' => filled($validated['link_url'] ?? null) ? trim($validated['link_url']) : null,
                'placement' => AdPlacement::from($validated['placement']),
                'locale' => filled($validated['locale'] ?? null) ? $validated['locale'] : null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            if ($image instanceof UploadedFile) {
                // A stream is bound as a LOB, which a PostgreSQL bytea column requires.
                $advertisement->image_data = fopen((string) $image->getRealPath(), 'rb');
                $advertisement->image_mime_type = $imageMimeType;
                $advertisement->image_checksum = (string) hash_file('sha256', (string) $image->getRealPath());
            }

            $advertisement->save();

            return $advertisement;
        });
    }

    /**
     * The MIME type read from the image bytes themselves, since the declared type and the extension
     * come from the client; null when the bytes are not a JPEG, PNG or WebP image.
     */
    private function detectImageMimeType(UploadedFile $image): ?string
    {
        $size = @getimagesize((string) $image->getRealPath());
        $mimeType = is_array($size) ? $size['mime'] : null;

        return in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true) ? $mimeType : null;
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'title' => __('admin.advertisements.fields.title'),
            'link_url' => __('admin.advertisements.fields.link_url'),
            'placement' => __('admin.advertisements.fields.placement'),
            'locale' => __('admin.advertisements.fields.locale'),
            'is_active' => __('admin.advertisements.fields.is_active'),
            'starts_at' => __('admin.advertisements.fields.starts_at'),
            'ends_at' => __('admin.advertisements.fields.ends_at'),
            'image' => __('admin.advertisements.fields.image'),
        ];
    }
}
