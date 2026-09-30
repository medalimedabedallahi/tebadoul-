<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LogoutUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutController extends Controller
{
    public function __invoke(Request $request, LogoutUser $logoutUser): Response
    {
        $logoutUser->handle($request->user());

        return response()->noContent();
    }
}
