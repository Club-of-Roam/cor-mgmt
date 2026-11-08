<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\MailTransport;
use Cor\Mgmt\Application\Contracts\RandomSource;

/** Controlled worker: delivery requires an explicit event-specific gate and transport. */
final class MailQueueWorker
{
    /** @var \Closure(int):bool */
    private readonly \Closure $enabled;

    /** @param callable(int):bool|null $enabled */
    public function __construct(private readonly MailQueue $queue, private readonly MailTransport $transport, private readonly RandomSource $random, ?callable $enabled = null)
    {
        $this->enabled = $enabled === null ? static fn (int $race): bool => false : \Closure::fromCallable($enabled);
    }

    /** At most one message; no transport under the domain transaction or advisory lock. */
    public function runOne(int $race, int $id = 0, ?callable $beforeClaim = null): string
    {
        if ($race < 1 || !($this->enabled)($race)) {
            throw new CommunicationException('mail_delivery_disabled');
        }
        $this->queue->assertDispatchable();
        $token = bin2hex($this->random->bytes(32));
        $message = $this->queue->claim($race, $token, $id, $beforeClaim);
        if ($message === null) {
            return 'empty';
        }
        try {
            $accepted = $this->transport->send($message['recipient'] ?? '', $message['subject'] ?? '', $message['message'] ?? '', "From: Tramprennen <no-reply@tramprennen.org>\r\nContent-Type: text/plain; charset=UTF-8");
            $status = $accepted ? 'sent' : 'failed';
        } catch (\Throwable) {
            // The provider may already have accepted it. Never blindly resend an exception.
            $status = 'uncertain';
        }
        $this->queue->finish($race, (int) $message['id'], $token, $status);
        return $status;
    }
}
