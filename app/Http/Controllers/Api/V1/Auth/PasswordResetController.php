<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RequestPasswordReset;
use App\Actions\Auth\ResetPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    /**
     * Always answers 202 with the same body, whether or not the contact belongs to an account.
     */
    public function forgot(ForgotPasswordRequest $request, RequestPasswordReset $requestReset): JsonResponse
    {
        $requestReset->handle($request->string('contact')->toString());

        return response()->json([
            'data' => ['message' => __('If the contact belongs to an account, a password reset code has been sent.')],
        ], 202);
    }

    public function reset(ResetPasswordRequest $request, ResetPassword $resetPassword): JsonResponse
    {
        $resetPassword->handle(
            $request->string('contact')->toString(),
            $request->string('code')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(['data' => ['reset' => true]]);
    }
}
