<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Requests\Stock\StockReportRequest;
use App\Resources\StockResource;
use App\Services\StockService;

class StockController extends ApiController
{
    public function index()
    {
        $query = $this->request->getGet();
        $form  = new StockReportRequest();

        if (! $form->validate($query)) {
            return ApiResponse::error('Parameter tidak valid.', 422, $form->errors());
        }

        $onDate = ($query['on_date'] ?? '') !== '' ? $query['on_date'] : null;
        $report = (new StockService())->report($onDate);

        return ApiResponse::success(StockResource::collection($report));
    }
}
