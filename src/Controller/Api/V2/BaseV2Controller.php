<?php

declare(strict_types=1);

namespace App\Controller\Api\V2;

use App\Controller\Api\BaseApiController;

abstract class BaseV2Controller extends BaseApiController
{
    protected const API_VERSION = 'v2';

    protected function getApiVersion(): string
    {
        return self::API_VERSION;
    }
}