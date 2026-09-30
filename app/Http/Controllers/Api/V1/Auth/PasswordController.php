<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, ChangePassword $changePassword): Response
    {
        $changePassword->handle(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return response()->noContent();
    }
}
