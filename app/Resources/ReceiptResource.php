<?php

namespace App\Resources;

use DateTimeImmutable;
use DateTimeZone;

class ReceiptResource
{
    public static function item(array $r): array
    {
        $updated = $r['updated_by'] !== null;

        $out = [
            'id'           => (int) $r['id'],
            'reference_no' => $r['reference_no'],
            'supplier'     => ['id' => (int) $r['supplier_id'], 'name' => $r['supplier_name']],
            'received_at'  => self::iso($r['received_at']),
            'created_by'   => ['id' => (int) $r['created_by'], 'name' => $r['created_by_name']],
            'created_at'   => self::iso($r['created_at']),
            // Belum pernah diubah: pengubah terakhir dan waktunya kosong (null).
            'updated_by'   => $updated ? ['id' => (int) $r['updated_by'], 'name' => $r['updated_by_name']] : null,
            'updated_at'   => $updated ? self::iso($r['updated_at']) : null,
            'items'        => array_map(static fn (array $i) => [
                'medicine_id' => (int) $i['medicine_id'],
                'code'        => $i['code'],
                'name'        => $i['name'],
                'unit'        => $i['unit'],
                'batch_no'    => $i['batch_no'],
                'expires_on'  => $i['expires_on'],
                'quantity'    => (int) $i['quantity'],
            ], $r['items'] ?? []),
        ];

        if (isset($r['history'])) {
            $out['history'] = array_map(static fn (array $h) => [
                'action' => $h['action'],
                'user'   => ['id' => (int) $h['user_id'], 'name' => $h['user_name']],
                'at'     => self::iso($h['created_at']),
            ], $r['history']);
        }

        return $out;
    }

    public static function collection(array $rows): array
    {
        return array_map([self::class, 'item'], $rows);
    }

    private static function iso(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('Asia/Jakarta')))->format('c');
    }
}
