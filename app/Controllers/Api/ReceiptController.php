<?php

namespace App\Controllers\Api;

use App\Libraries\ApiResponse;
use App\Requests\Receipt\StoreReceiptRequest;
use App\Requests\Receipt\UpdateReceiptRequest;
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
            ReceiptResource::collection($this->service->all(), $this->user())
        ));
    }

    public function show(int $id)
    {
        return $this->handle(fn () => ApiResponse::success(
            ReceiptResource::item($this->service->find($id), $this->user())
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

            $user = $this->user();
            $id   = $this->service->create($data, $user);

            return ApiResponse::success(
                ReceiptResource::item($this->service->find($id), $user),
                'Penerimaan berhasil dibuat.',
                201
            );
        });
    }
    
    public function update(int $id)
    {
        return $this->handle(function () use ($id) {
            $user = $this->user();                       // dari sesi, bukan body

            $this->service->ensureCanUpdate($id, $user); // 404 lalu 403, sebelum validasi

            $data = $this->input();
            $form = new UpdateReceiptRequest();

            if (! $form->validate($data)) {
                return ApiResponse::error('Data penerimaan tidak valid.', 422, $form->errors());
            }

            $this->service->update($id, $data, $user);   // diperiksa ulang di dalam transaksi

            return ApiResponse::success(
                ReceiptResource::item($this->service->find($id), $user),
                'Penerimaan berhasil diperbarui.'
            );
        });
    }
}
