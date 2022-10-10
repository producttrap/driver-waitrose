<?php

declare(strict_types=1);

namespace ProductTrap\waitrose\Tests;

use ProductTrap\ProductTrapServiceProvider;
use ProductTrap\Waitrose\WaitroseServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [ProductTrapServiceProvider::class, WaitroseServiceProvider::class];
    }
}
