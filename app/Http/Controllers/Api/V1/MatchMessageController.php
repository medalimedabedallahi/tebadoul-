<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Messaging\ListMatchMessages;
use App\Actions\Messaging\MarkMatchMessageRead;
use App\Actions\Messaging\SendMatchMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Messaging\ListMatchMessagesRequest;
use App\Http\Requests\Api\V1\Messaging\StoreMatchMessageRequest;
use App\Http\Resources\MatchMessageResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MatchMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListMatchMessagesRequest $request, string $match, ListMatchMessages $messages): AnonymousResourceCollection
    {
        return MatchMessageResource::collection($messages->handle(
            $this->user($request),
            $match,
            $request->integer('page', 1),
            $request->integer('per_page', 50),
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMatchMessageRequest $request, string $match, SendMatchMessage $send): MatchMessageResource
    {
        return new MatchMessageResource($send->handle(
            $this->user($request),
            $match,
            $request->string('body')->toString(),
        ));
    }

    /**
     * Display the specified resource.
     */
    public function read(Request $request, string $match, string $message, MarkMatchMessageRead $markRead): MatchMessageResource
    {
        return new MatchMessageResource($markRead->handle($this->user($request), $match, $message));
    }

    /**
     * Update the specified resource in storage.
     */
    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
