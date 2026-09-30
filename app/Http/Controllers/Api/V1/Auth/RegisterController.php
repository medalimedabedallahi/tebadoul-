<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    /**
     * Always answers 202 with the same body, whether the contact is new or already registered.
     */
    public function __invoke(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $registerUser->handle(
            name: $request->string('name')->toString(),
            email: $request->input('email'),
            phone: $request->input('phone'),
            password: $request->string('password')->toString(),
            locale: $request->input('locale'),
        );

        return response()->json([
            'data' => ['message' => __('If the information provided is valid, a verification code has been sent.')],
        ], 202);
    }
}
