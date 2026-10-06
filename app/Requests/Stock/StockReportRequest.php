<?php

namespace App\Requests\Stock;

use App\Requests\BaseRequest;

class StockReportRequest extends BaseRequest
{
    protected function rules(): array
    {
        return [
            'on_date' => 'permit_empty|valid_date[Y-m-d]',
        ];
    }

    protected function messages(): array
    {
        return [
            'on_date' => [
                'valid_date' => 'on_date harus berformat YYYY-MM-DD dan merupakan tanggal yang valid.',
            ],
        ];
    }
}
