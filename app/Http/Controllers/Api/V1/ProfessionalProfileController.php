<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profiles\SaveProfessionalProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateProfessionalProfileRequest;
use App\Http\Resources\ProfessionalProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProfessionalProfileController extends Controller
{
    public function show(Request $request): ProfessionalProfileResource
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('manageProfessionalProfile', $user);

        $profile = $user->professionalProfile()
            ->with(['sector', 'profession', 'specialty', 'grade', 'moughataa.wilaya', 'establishment'])
            ->firstOrFail();

        return new ProfessionalProfileResource($profile);
    }

    public function update(UpdateProfessionalProfileRequest $request, SaveProfessionalProfile $saveProfile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{sector: string, profession: string, specialty?: string|null, grade?: string|null, wilaya: string, moughataa: string, establishment?: string|null, professional_identifier?: string|null} $validated */
        $validated = $request->validated();
        $profile = $saveProfile->handle($user, $validated);

        return (new ProfessionalProfileResource($profile))
            ->response()
            ->setStatusCode($profile->wasRecentlyCreated ? 201 : 200);
    }
}
