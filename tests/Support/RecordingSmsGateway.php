<?php

namespace Tests\Support;

use App\Contracts\SmsGateway;

/**
 * Test double that keeps every SMS in memory instead of sending it.
 */
final class RecordingSmsGateway implements SmsGateway
{
    /**
     * @var list<array{to: string, message: string}>
     */
    public array $sent = [];

    public function send(string $to, string $message): void
    {
        $this->sent[] = ['to' => $to, 'message' => $message];
    }

    /**
     * The digits of the last code sent, read from the message.
     */
    public function lastCode(): ?string
    {
        $last = end($this->sent);

        return $last !== false && preg_match('/\b(\d{4,9})\b/', $last['message'], $matches) === 1 ? $matches[1] : null;
    }
}
