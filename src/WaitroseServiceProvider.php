<?php

declare(strict_types=1);

namespace ProductTrap\Waitrose;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use ProductTrap\Contracts\Factory;
use ProductTrap\ProductTrap;

class WaitroseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var ProductTrap $factory */
        $factory = $this->app->make(Factory::class);

        $factory->extend(Waitrose::IDENTIFIER, function () {
            /** @var CacheRepository $cache */
            $cache = $this->app->make(CacheRepository::class);

            return new Waitrose(
                cache: $cache,
            );
        });
    }
}
