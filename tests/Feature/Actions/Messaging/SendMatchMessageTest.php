<?php

namespace Tests\Feature\Actions\Messaging;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Messaging\SendMatchMessage;
use App\Actions\Trust\BlockUser;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Exceptions\Trust\InteractionBlockedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class SendMatchMessageTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_participants_can_message_only_after_mutual_acceptance(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();

        $this->expectException(InvalidMatchTransitionException::class);
        app(SendMatchMessage::class)->handle($rosso, $match->public_id, 'Bonjour');
    }

    public function test_an_agreement_allows_messages_but_a_block_stops_them(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);

        $message = app(SendMatchMessage::class)->handle($rosso, $match->public_id, '  Bonjour  ');
        $this->assertSame('Bonjour', $message->body);
        $this->assertSame($rosso->id, $message->sender_id);

        app(BlockUser::class)->handle($hodh, $match->public_id);

        $this->expectException(InteractionBlockedException::class);
        app(SendMatchMessage::class)->handle($rosso, $match->public_id, 'Encore');
    }
}
