<?php

declare(strict_types=1);

namespace Panulat\Foundation;

use Panulat\Container\Container;

interface ServiceProviderInterface
{
    public function register(Container $container): void;

    public function boot(Application $app): void;
}
