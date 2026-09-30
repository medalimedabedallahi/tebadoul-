<?php

namespace App\Actions\Profiles;

use App\Jobs\RecomputeMatches;
use App\Models\Establishment;
use App\Models\Grade;
use App\Models\Moughataa;
use App\Models\Profession;
use App\Models\ProfessionalProfile;
use App\Models\Sector;
use App\Models\Specialty;
use App\Models\User;
use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates or replaces the professional profile of an account (PUT semantics: every field is
 * given each time). References are given by code and must be active and consistent:
 *
 * - the profession belongs to the sector, the specialty and the grade to the profession;
 * - a specialty (or grade) is required when the profession has at least one active specialty
 *   (or grade), and must be empty otherwise;
 * - the moughataa belongs to the wilaya;
 * - the optional establishment is in that moughataa and that sector.
 *
 * A reference that is not consistent is reported as a validation error on its own field, so the
 * API (422) and the web form show it next to the right input.
 */
final class SaveProfessionalProfile
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'sector' => ['required', 'string', 'max:64'],
            'profession' => ['required', 'string', 'max:64'],
            'specialty' => ['nullable', 'string', 'max:64'],
            'grade' => ['nullable', 'string', 'max:64'],
            'wilaya' => ['required', 'string', 'max:64'],
            'moughataa' => ['required', 'string', 'max:64'],
            'establishment' => ['nullable', 'string', 'max:64'],
            'professional_identifier' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @param  array{sector: string, profession: string, specialty?: string|null, grade?: string|null, wilaya: string, moughataa: string, establishment?: string|null, professional_identifier?: string|null}  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input): ProfessionalProfile
    {
        Gate::forUser($user)->authorize('manageProfessionalProfile', $user);

        $attributes = $this->resolveReferences($input);

        return DB::transaction(function () use ($user, $attributes): ProfessionalProfile {
            // Serializes concurrent saves of the same account (the profile row may not exist yet).
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $profile = $user->professionalProfile()->firstOrNew();
            $profile->fill($attributes)->save();
            RecomputeMatches::forUser($user);

            return $profile->load(['sector', 'profession', 'specialty', 'grade', 'moughataa.wilaya', 'establishment']);
        });
    }

    /**
     * @param  array<string, string|null>  $input
     * @return array<string, int|string|null>
     *
     * @throws ValidationException
     */
    private function resolveReferences(array $input): array
    {
        $errors = [];

        $sector = $this->find(Sector::query(), $input['sector']);
        $errors['sector'] = $sector === null;

        $profession = $sector === null ? null : $this->find(Profession::query()->where('sector_id', $sector->getKey()), $input['profession']);
        $errors['profession'] = $profession === null;

        [$specialty, $errors['specialty']] = $this->resolveChild(Specialty::query(), $profession, $input['specialty'] ?? null);
        [$grade, $errors['grade']] = $this->resolveChild(Grade::query(), $profession, $input['grade'] ?? null);

        $wilaya = $this->find(Wilaya::query(), $input['wilaya']);
        $errors['wilaya'] = $wilaya === null;

        $moughataa = $wilaya === null ? null : $this->find(Moughataa::query()->where('wilaya_id', $wilaya->getKey()), $input['moughataa']);
        $errors['moughataa'] = $moughataa === null;

        $establishment = null;

        if (($input['establishment'] ?? null) !== null && $input['establishment'] !== '') {
            $establishment = $sector === null || $moughataa === null ? null : $this->find(
                Establishment::query()->where('sector_id', $sector->getKey())->where('moughataa_id', $moughataa->getKey()),
                $input['establishment'],
            );
            $errors['establishment'] = $establishment === null;
        }

        $messages = [];

        foreach (array_filter($errors) as $field => $error) {
            $messages[$field] = __($error === 'required' ? 'validation.required' : 'profile.errors.invalid', [
                'attribute' => __("profile.fields.$field"),
            ]);
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        $identifier = trim((string) ($input['professional_identifier'] ?? ''));

        return [
            'sector_id' => $sector?->getKey(),
            'profession_id' => $profession?->getKey(),
            'specialty_id' => $specialty?->getKey(),
            'grade_id' => $grade?->getKey(),
            'moughataa_id' => $moughataa?->getKey(),
            'establishment_id' => $establishment?->getKey(),
            'professional_identifier' => $identifier === '' ? null : $identifier,
        ];
    }

    /**
     * A specialty or a grade of the profession: required when the profession has active ones,
     * refused otherwise.
     *
     * @template TModel of Specialty|Grade
     *
     * @param  Builder<TModel>  $query
     * @return array{0: TModel|null, 1: bool|'required'}
     */
    private function resolveChild(Builder $query, ?Profession $profession, ?string $code): array
    {
        if ($profession === null) {
            return [null, false];
        }

        $query->where('profession_id', $profession->getKey());

        if ($code === null || $code === '') {
            return [null, (clone $query)->where('active', true)->exists() ? 'required' : false];
        }

        $entry = $this->find($query, $code);

        return [$entry, $entry === null];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|null
     */
    private function find(Builder $query, ?string $code): ?Model
    {
        return $code === null ? null : $query->where('active', true)->where('code', $code)->first();
    }
}
