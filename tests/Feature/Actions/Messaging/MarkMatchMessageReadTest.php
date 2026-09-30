<?php

namespace Tests\Feature\Actions\Messaging;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Messaging\MarkMatchMessageRead;
use App\Actions\Messaging\SendMatchMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesMatchedPair;
use Tests\TestCase;

class MarkMatchMessageReadTest extends TestCase
{
    use CreatesMatchedPair;
    use RefreshDatabase;

    public function test_only_the_recipient_can_mark_a_message_read_and_the_action_is_idempotent(): void
    {
        [$rosso, $hodh, $match] = $this->createMatchedPair();
        app(InviteToMatch::class)->handle($rosso, $match->public_id);
        app(AcceptMatch::class)->handle($hodh, $match->public_id);
        $message = app(SendMatchMessage::class)->handle($rosso, $match->public_id, 'Bonjour');

        $read = app(MarkMatchMessageRead::class)->handle($hodh, $match->public_id, $message->public_id);
        $firstReadAt = $read->read_at;
        $this->assertNotNull($firstReadAt);
        $this->assertTrue($firstReadAt->equalTo(
            app(MarkMatchMessageRead::class)->handle($hodh, $match->public_id, $message->public_id)->read_at,
        ));

        $this->expectException(ModelNotFoundException::class);
        app(MarkMatchMessageRead::class)->handle($rosso, $match->public_id, $message->public_id);
    }
}
