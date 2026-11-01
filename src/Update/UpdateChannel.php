<?php

namespace App\Update;

final class UpdateChannel
{
    public const STABLE = 'stable';

    public const PRERELEASE = 'prerelease';

    public static function isValid(string $channel): bool
    {
        return $channel === self::STABLE || $channel === self::PRERELEASE;
    }
}
