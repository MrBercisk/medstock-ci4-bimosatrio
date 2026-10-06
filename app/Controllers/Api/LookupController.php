<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Services\LookupService;

class LookupController extends ApiController
{
    public function suppliers()
    {
        return $this->handle(fn () => ApiResponse::success(
            (new LookupService())->activeSuppliers()
        ));
    }

    public function medicines()
    {
        return $this->handle(fn () => ApiResponse::success(
            (new LookupService())->activeMedicines()
        ));
    }

    public function batches()
    {
        return $this->handle(fn () => ApiResponse::success(
            (new LookupService())->getBatches()
        ));
    }
}
