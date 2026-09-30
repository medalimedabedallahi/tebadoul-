<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\SendContactVerification;
use App\Actions\Auth\VerifyContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SendContactVerificationRequest;
use App\Http\Requests\Api\V1\Auth\VerifyContactRequest;
use Illuminate\Http\JsonResponse;

class ContactVerificationController extends Controller
{
    /**
     * Always answers 202 with the same body, whether or not a code was actually sent.
     */
    public function send(SendContactVerificationRequest $request, SendContactVerification $sendVerification): JsonResponse
    {
        $sendVerification->handle($request->string('contact')->toString());

        return response()->json([
            'data' => ['message' => __('If the contact can be verified, a verification code has been sent.')],
        ], 202);
    }

    public function verify(VerifyContactRequest $request, VerifyContact $verifyContact): JsonResponse
    {
        $verifyContact->handle(
            $request->string('contact')->toString(),
            $request->string('code')->toString(),
        );

        return response()->json(['data' => ['verified' => true]]);
    }
}
