<?php

namespace App\Security;

enum TotpVerdict
{
    case Accepted;
    case Rejected;
    case Throttled;
}
