<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\DeleteAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\DeleteAccountRequest;
use App\Models\User;
use Illuminate\Http\Response;

class DeleteAccountController extends Controller
{
    /**
     * Deletes (anonymizes) the account; every token, including this one, is revoked.
     */
    public function __invoke(DeleteAccountRequest $request, DeleteAccount $deleteAccount): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deleteAccount->handle($user, $request->string('password')->toString(), $request->ip());

        return response()->noContent();
    }
}
