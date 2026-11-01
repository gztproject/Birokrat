<?php

namespace App\Tests\Unit;

use App\Update\VersionComparator;
use PHPUnit\Framework\TestCase;

class VersionComparatorTest extends TestCase
{
    public function testStableAndPrereleaseTags(): void
    {
        $this->assertTrue(VersionComparator::isStable('1.2.3'));
        $this->assertTrue(VersionComparator::isStable('v1.2.3'));
        $this->assertFalse(VersionComparator::isStable('1.2.3-rc.1'));
        $this->assertTrue(VersionComparator::isPrerelease('1.2.3-rc.1'));
        $this->assertFalse(VersionComparator::isPrerelease('1.2.3'));
        $this->assertFalse(VersionComparator::isPrerelease('1.2.3-stable'));
        $this->assertFalse(VersionComparator::isSelectable('1.2.3-stable'));
    }

    public function testInstalledPrereleaseIsNotOfferedAnOlderStableTag(): void
    {
        $this->assertFalse(VersionComparator::isNewer('1.3.0-rc.1', '1.2.0'));
    }

    public function testLeadingVDoesNotCountAsANewerRelease(): void
    {
        $this->assertFalse(VersionComparator::isNewer('v1.2.0', '1.2.0'));
        $this->assertTrue(VersionComparator::isNewer('1.2.0', 'v1.3.0'));
    }

    public function testDevIsBehindARealTag(): void
    {
        $this->assertTrue(VersionComparator::isNewer('dev', '1.2.0'));
        $this->assertFalse(VersionComparator::isNewer('1.2.0', '1.2.0'));
    }
}
