<?php

namespace App\Update;

final class VersionComparator
{
    public static function isStable(string $version): bool
    {
        return (bool) preg_match('/^v?\d+\.\d+\.\d+$/i', trim($version));
    }

    public static function isPrerelease(string $version): bool
    {
        return (bool) preg_match('/^v?\d+\.\d+\.\d+-(?!stable$)[0-9A-Za-z.]+$/i', trim($version));
    }

    public static function isSelectable(string $version): bool
    {
        return self::isStable($version) || self::isPrerelease($version);
    }

    public static function normalize(string $version): string
    {
        $normalized = strtolower(trim($version));
        if (str_starts_with($normalized, 'v') && preg_match('/^v\d/', $normalized)) {
            $normalized = substr($normalized, 1);
        }

        return $normalized;
    }

    public static function isNewer(string $current, string $candidate): bool
    {
        if (!self::isSelectable($candidate)) {
            return false;
        }

        return version_compare(self::normalize($candidate), self::normalize($current), '>');
    }
}
