<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\References\ListReferences;
use App\Enums\ReferenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\References\ListEstablishmentsRequest;
use App\Http\Requests\Api\V1\References\ListReferencesRequest;
use App\Http\Resources\ReferenceEntryResource;
use App\Models\Wilaya;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public, read-only reference data (PRD section 10.3). Only active entries are returned.
 */
class ReferenceController extends Controller
{
    public function __construct(private readonly ListReferences $listReferences) {}

    public function sectors(): AnonymousResourceCollection
    {
        return ReferenceEntryResource::collection($this->listReferences->handle(ReferenceType::Sectors));
    }

    public function professions(ListReferencesRequest $request): AnonymousResourceCollection
    {
        return ReferenceEntryResource::collection($this->listReferences->handle(
            ReferenceType::Professions,
            array_filter(['sector_code' => $request->validated('sector')]),
        ));
    }

    public function specialties(ListReferencesRequest $request): AnonymousResourceCollection
    {
        return ReferenceEntryResource::collection($this->listReferences->handle(
            ReferenceType::Specialties,
            array_filter(['profession_code' => $request->validated('profession')]),
        ));
    }

    public function grades(ListReferencesRequest $request): AnonymousResourceCollection
    {
        return ReferenceEntryResource::collection($this->listReferences->handle(
            ReferenceType::Grades,
            array_filter(['profession_code' => $request->validated('profession')]),
        ));
    }

    public function wilayas(): AnonymousResourceCollection
    {
        return ReferenceEntryResource::collection($this->listReferences->handle(ReferenceType::Wilayas));
    }

    public function moughataas(string $wilayaCode): AnonymousResourceCollection
    {
        abort_unless(Wilaya::query()->active()->where('code', $wilayaCode)->exists(), 404);

        return ReferenceEntryResource::collection($this->listReferences->handle(
            ReferenceType::Moughataas,
            ['wilaya_code' => $wilayaCode],
        ));
    }

    public function establishments(ListEstablishmentsRequest $request): AnonymousResourceCollection
    {
        return ReferenceEntryResource::collection($this->listReferences->establishments(
            sectorCode: $request->validated('sector'),
            wilayaCode: $request->validated('wilaya'),
            moughataaCode: $request->validated('moughataa'),
            search: $request->validated('q'),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 20),
        ));
    }
}
