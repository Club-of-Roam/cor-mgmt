<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

enum RacePhase: int
{
    case BeforeRegistration = 0;
    case RegistrationOpen = 1;
    case RegistrationClosed = 2;
    case Started = 3;
    case Archived = 4;
}
