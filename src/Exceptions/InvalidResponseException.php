<?php

declare(strict_types=1);

namespace ProductTrap\Waitrose\Exceptions;

use ProductTrap\Contracts\ProductTrapException;

class InvalidResponseException extends \RuntimeException implements ProductTrapException
{
}
