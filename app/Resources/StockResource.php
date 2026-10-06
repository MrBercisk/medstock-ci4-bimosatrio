<?php

namespace App\Resources;

class StockResource
{
    public static function item(array $m): array
    {
        return [
            'medicine_id'        => $m['medicine_id'],
            'code'               => $m['code'],
            'name'               => $m['name'],
            'unit'               => $m['unit'],
            'physical_quantity'  => $m['available_quantity'] + $m['expired_quantity'],
            'available_quantity' => $m['available_quantity'],
            'expired_quantity'   => $m['expired_quantity'],
            'available_batches'  => $m['available_batches'],
            'expired_batches'    => $m['expired_batches'],
        ];
    }

    public static function collection(array $report): array
    {
        return [
            'on_date'   => $report['on_date'],
            'medicines' => array_map([self::class, 'item'], $report['medicines']),
        ];
    }
}
