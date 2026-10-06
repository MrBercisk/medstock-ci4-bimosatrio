<?php

namespace App\Requests\Receipt;

use App\Requests\BaseRequest;

class StoreReceiptRequest extends BaseRequest
{
    protected function rules(): array
    {
        return [
            'reference_no'        => 'required|max_length[50]',
            'supplier_id'         => 'required|is_natural_no_zero',
            'received_at'         => 'required',
            'items'               => 'required',
            'items.*.medicine_id' => 'required|is_natural_no_zero',
            'items.*.batch_no'    => 'required|max_length[50]',
            'items.*.expires_on'  => 'required|valid_date[Y-m-d]',
            'items.*.quantity'    => 'required|is_natural_no_zero',
        ];
    }

    protected function messages(): array
    {
        return [
            'reference_no' => ['required' => 'reference_no wajib diisi.', 'max_length' => 'reference_no maksimal 50 karakter.'],
            'supplier_id'  => ['required' => 'supplier_id wajib diisi.', 'is_natural_no_zero' => 'supplier_id harus bilangan bulat positif.'],
            'received_at'  => ['required' => 'received_at wajib diisi.'],
            'items'        => ['required' => 'Penerimaan harus berisi minimal satu item.'],
            'items.*.medicine_id' => ['required' => 'medicine_id wajib diisi.', 'is_natural_no_zero' => 'medicine_id harus bilangan bulat positif.'],
            'items.*.batch_no'    => ['required' => 'batch_no wajib diisi.', 'max_length' => 'batch_no maksimal 50 karakter.'],
            'items.*.expires_on'  => ['required' => 'expires_on wajib diisi.', 'valid_date' => 'expires_on harus tanggal valid berformat YYYY-MM-DD.'],
            'items.*.quantity'    => ['required' => 'quantity wajib diisi.', 'is_natural_no_zero' => 'quantity harus bilangan bulat positif.'],
        ];
    }

    public function validate(array $data): bool
    {
        // Rapikan bentuk items supaya aturan wildcard tidak error pada input aneh.
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        foreach ($items as $i => $item) {
            $item = is_array($item) ? $item : [];

            foreach (['medicine_id', 'batch_no', 'expires_on', 'quantity'] as $key) {
                $item[$key] ??= null;
            }

            $items[$i] = $item;
        }

        $data['items'] = $items;

        return parent::validate($data);
    }
}
