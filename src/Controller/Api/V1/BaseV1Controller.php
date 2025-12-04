<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\BaseApiController;
use App\Service\Api\ApiConfigService;

abstract class BaseV1Controller extends BaseApiController
{
    public function __construct(
        ApiConfigService $apiConfig
    ) {
        parent::__construct($apiConfig);
    }
    protected const API_VERSION = 'v1';

    protected function getApiVersion(): string
    {
        return self::API_VERSION;
    }
}