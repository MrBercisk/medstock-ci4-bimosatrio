<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Requests\Receipt\StoreReceiptRequest;
use App\Resources\ReceiptResource;
use App\Services\ReceiptService;

class ReceiptController extends ApiController
{
    private ReceiptService $service;

    public function __construct()
    {
        $this->service = new ReceiptService();
    }

    public function index()
    {
        return $this->handle(fn () => ApiResponse::success(
            ReceiptResource::collection($this->service->all())
        ));
    }

    public function show(int $id)
    {
        return $this->handle(fn () => ApiResponse::success(
            ReceiptResource::item($this->service->find($id))
        ));
    }

    public function store()
    {
        return $this->handle(function () {
            $data = $this->input();
            $form = new StoreReceiptRequest();

            if (! $form->validate($data)) {
                return ApiResponse::error('Data penerimaan tidak valid.', 422, $form->errors());
            }

            $id = $this->service->create($data, $this->user()); // pembuat dari sesi, bukan body

            return ApiResponse::success(
                ReceiptResource::item($this->service->find($id)),
                'Penerimaan berhasil dibuat.',
                201
            );
        });
    }
}
