<?php

namespace App\Actions\References;

use App\Contracts\ReferenceEntry;
use App\Enums\ReferenceType;
use App\Models\Establishment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Active reference entries, for the public `/api/v1/references` endpoints and the web forms.
 *
 * Only active entries are listed, sorted by their name in the current locale. Reference data is
 * public: no authorization is needed. Filters use codes, never internal identifiers.
 */
final class ListReferences
{
    /**
     * @return array<string, list<string>>
     */
    public static function establishmentRules(): array
    {
        return [
            'sector' => ['sometimes', 'required', 'string', 'max:64'],
            'wilaya' => ['sometimes', 'required', 'string', 'max:64'],
            'moughataa' => ['sometimes', 'required', 'string', 'max:64'],
            'q' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /**
     * Entries of a small reference table, optionally restricted to one parent (for example the
     * professions of the sector `education`). Establishments use {@see self::establishments()}.
     *
     * @param  array<string, string>  $parentCodes  Parent codes keyed by the CSV column name
     *                                              ({@see ReferenceType::parents()}).
     * @return Collection<int, Model&ReferenceEntry>
     */
    public function handle(ReferenceType $type, array $parentCodes = []): Collection
    {
        $query = $this->activeQuery($type);

        foreach ($type->parents() as $column => $parent) {
            if (isset($parentCodes[$column])) {
                $query->whereHas($parent['relation'], fn (Builder $parentQuery) => $parentQuery->where('code', $parentCodes[$column]));
            }
        }

        return $query->get();
    }

    /**
     * Establishments, filtered by sector, wilaya, moughataa and a name search, paginated.
     *
     * @return LengthAwarePaginator<int, Establishment>
     */
    public function establishments(
        ?string $sectorCode = null,
        ?string $wilayaCode = null,
        ?string $moughataaCode = null,
        ?string $search = null,
        int $page = 1,
        int $perPage = 20,
    ): LengthAwarePaginator {
        /** @var Builder<Establishment> $query */
        $query = $this->activeQuery(ReferenceType::Establishments);

        return $query
            ->with('moughataa.wilaya')
            ->when($sectorCode !== null, fn (Builder $query) => $query->whereHas('sector', fn (Builder $sector) => $sector->where('code', $sectorCode)))
            ->when($moughataaCode !== null, fn (Builder $query) => $query->whereHas('moughataa', fn (Builder $moughataa) => $moughataa->where('code', $moughataaCode)))
            ->when($wilayaCode !== null, fn (Builder $query) => $query->whereHas('moughataa.wilaya', fn (Builder $wilaya) => $wilaya->where('code', $wilayaCode)))
            ->when($search !== null, function (Builder $query) use ($search): void {
                $pattern = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $query->where(fn (Builder $names) => $names
                    ->whereRaw("lower(name_fr) like ? escape '\\'", [$pattern])
                    ->orWhereRaw("lower(name_ar) like ? escape '\\'", [$pattern]));
            })
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Builder<Model&ReferenceEntry>
     */
    private function activeQuery(ReferenceType $type): Builder
    {
        $nameColumn = app()->getLocale() === 'ar' ? 'name_ar' : 'name_fr';
        $relations = array_column($type->parents(), 'relation');

        return $type->modelClass()::query()
            ->where('active', true)
            ->with($relations)
            ->orderBy($nameColumn)
            ->orderBy('code');
    }
}
