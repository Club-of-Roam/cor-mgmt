<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\MailTransport;
use Cor\Mgmt\Application\Contracts\RandomSource;

/** Unregistered worker: delivery requires an explicit event-specific gate and transport. */
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
    public function runOne(int $race): string
    {
        if ($race < 1 || !($this->enabled)($race)) {
            throw new CommunicationException('mail_delivery_disabled');
        }
        $this->queue->assertDispatchable();
        $token = bin2hex($this->random->bytes(32));
        $message = $this->queue->claim($race, $token);
        if ($message === null) {
            return 'empty';
        }
        try {
            $accepted = $this->transport->send($message['recipient'] ?? '', $message['subject'] ?? '', $message['message'] ?? '', 'Content-Type: text/plain; charset=UTF-8');
            $status = $accepted ? 'sent' : 'failed';
        } catch (\Throwable) {
            // The provider may already have accepted it. Never blindly resend an exception.
            $status = 'uncertain';
        }
        $this->queue->finish($race, (int) $message['id'], $token, $status);
        return $status;
    }
}
