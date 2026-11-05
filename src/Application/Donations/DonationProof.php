<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Donations;

use Cor\Mgmt\Domain\EuroAmount;

/** An observed public Betterplace opinion, not a signed settlement/refund notification. */
final readonly class DonationProof
{
    public function __construct(public string $opinion, public EuroAmount $amount, public int $confirmedAt)
    {
    }
}
