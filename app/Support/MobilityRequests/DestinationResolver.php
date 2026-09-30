<?php

namespace App\Support\MobilityRequests;

use App\Models\Establishment;
use App\Models\MobilityRequest;
use App\Models\Moughataa;
use App\Models\ProfessionalProfile;
use App\Models\RequestDestination;
use App\Models\Wilaya;
use Illuminate\Validation\ValidationException;

/**
 * Turns destinations given by code into rows of `request_destinations`, and checks them:
 *
 * - every reference is active; the moughataa belongs to the wilaya; an establishment needs a
 *   moughataa, must be in it and, when the owner has a profile, in the owner's sector;
 * - the same destination is not given twice;
 * - a destination is not the current assignment: same moughataa as the profile, with no
 *   establishment or the current establishment.
 *
 * Errors are keyed `destinations.<index>.<field>` so that forms show them on the right row.
 */
final class DestinationResolver
{
    /**
     * @param  list<array{wilaya?: string|null, moughataa?: string|null, establishment?: string|null}>  $destinations
     * @return list<array{priority: int, wilaya_id: int, moughataa_id: int|null, establishment_id: int|null}>
     *
     * @throws ValidationException
     */
    public function resolve(array $destinations, ?ProfessionalProfile $profile): array
    {
        $errors = [];
        $rows = [];
        $seen = [];

        foreach ($destinations as $index => $destination) {
            $field = "destinations.$index";
            $wilayaCode = $destination['wilaya'] ?? null;
            $wilaya = $wilayaCode === null || $wilayaCode === '' ? null : Wilaya::query()->where('active', true)->where('code', $wilayaCode)->first();

            if ($wilaya === null) {
                $errors["$field.wilaya"] = $this->invalid('wilaya');

                continue;
            }

            $moughataa = null;
            $moughataaCode = $destination['moughataa'] ?? null;

            if ($moughataaCode !== null && $moughataaCode !== '') {
                $moughataa = Moughataa::query()->where('active', true)->where('wilaya_id', $wilaya->id)->where('code', $moughataaCode)->first();

                if ($moughataa === null) {
                    $errors["$field.moughataa"] = $this->invalid('moughataa');

                    continue;
                }
            }

            $establishment = null;
            $establishmentCode = $destination['establishment'] ?? null;

            if ($establishmentCode !== null && $establishmentCode !== '') {
                $establishment = $moughataa === null ? null : Establishment::query()
                    ->where('active', true)
                    ->where('moughataa_id', $moughataa->id)
                    ->when($profile !== null, fn ($query) => $query->where('sector_id', $profile->sector_id))
                    ->where('code', $establishmentCode)
                    ->first();

                if ($establishment === null) {
                    $errors["$field.establishment"] = $this->invalid('establishment');

                    continue;
                }
            }

            $key = implode(':', [$wilaya->id, $moughataa?->id, $establishment?->id]);

            if (isset($seen[$key])) {
                $errors["$field.wilaya"] = __('requests.errors.duplicate_destination');

                continue;
            }

            $seen[$key] = true;

            if ($profile !== null && $moughataa !== null && $moughataa->id === $profile->moughataa_id
                && ($establishment === null || $establishment->id === $profile->establishment_id)) {
                $errors["$field.moughataa"] = __('requests.errors.destination_is_current_assignment');

                continue;
            }

            $rows[] = [
                'priority' => count($rows) + 1,
                'wilaya_id' => $wilaya->id,
                'moughataa_id' => $moughataa?->id,
                'establishment_id' => $establishment?->id,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    /**
     * The stored destinations of `$request`, back in the input format, to validate them again
     * (for example at publication, as references may have been deactivated since).
     *
     * @return list<array{wilaya: string, moughataa: string|null, establishment: string|null}>
     */
    public function toInput(MobilityRequest $request): array
    {
        return $request->destinations()
            ->with(['wilaya', 'moughataa', 'establishment'])
            ->get()
            ->map(fn (RequestDestination $destination): array => [
                'wilaya' => $destination->wilaya->code,
                'moughataa' => $destination->moughataa?->code,
                'establishment' => $destination->establishment?->code,
            ])
            ->values()
            ->all();
    }

    private function invalid(string $field): string
    {
        return __('requests.errors.invalid_reference', ['attribute' => __("requests.fields.$field")]);
    }
}
