<?php

namespace App\Version;

interface VersionSource
{
    public function current(): string;
}
