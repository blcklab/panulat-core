<?php

declare(strict_types=1);

namespace Panulat\Auth;

interface Authenticatable
{
    public function getAuthIdentifier(): string|int;
}
