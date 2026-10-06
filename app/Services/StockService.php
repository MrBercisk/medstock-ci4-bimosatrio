<?php

namespace App\Services;

use CodeIgniter\Database\ConnectionInterface;
use DateTime;
use DateTimeZone;

class StockService
{
    /**
     * Stok fisik per batch = stok awal + penerimaan - pemakaian.
     * Tiga sumber digabung dengan UNION ALL, lalu dijumlahkan per (medicine_id, batch_no).
     * CAST ke SIGNED karena kolom quantity bertipe UNSIGNED (pemakaian harus negatif).
     * LEFT JOIN supaya obat aktif tanpa batch tetap muncul.
     */
    private const SQL = <<<'SQL'
        SELECT m.id AS medicine_id, m.code, m.name, m.unit,
               b.batch_no, b.expires_on, b.qty
        FROM medicines m
        LEFT JOIN (
            SELECT medicine_id, batch_no, MAX(expires_on) AS expires_on, SUM(qty) AS qty
            FROM (
                SELECT medicine_id, batch_no, expires_on, CAST(quantity AS SIGNED) AS qty
                  FROM seed_batch_stock
                UNION ALL
                SELECT medicine_id, batch_no, expires_on, CAST(quantity AS SIGNED)
                  FROM receipt_items
                UNION ALL
                SELECT medicine_id, batch_no, NULL, -CAST(quantity AS SIGNED)
                  FROM stock_usage
            ) t
            GROUP BY medicine_id, batch_no
        ) b ON b.medicine_id = m.id
        WHERE m.is_active = 1
        ORDER BY m.id, b.batch_no
        SQL;

    private ConnectionInterface $db;

    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function today(): string
    {
        return (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }

    /**
     * @return array{on_date: string, medicines: array<int, array>}
     */
    public function report(?string $onDate = null): array
    {
        $onDate ??= $this->today();
        $rows      = $this->db->query(self::SQL)->getResultArray();
        $medicines = [];

        foreach ($rows as $row) {
            $id = (int) $row['medicine_id'];

            $medicines[$id] ??= [
                'medicine_id'        => $id,
                'code'               => $row['code'],
                'name'               => $row['name'],
                'unit'               => $row['unit'],
                'available_quantity' => 0,
                'expired_quantity'   => 0,
                'available_batches'  => [],
                'expired_batches'    => [],
            ];

            // Obat tanpa batch: tetap tampil dengan jumlah nol.
            if ($row['batch_no'] === null) {
                continue;
            }

            $qty = (int) $row['qty'];

            // Batch dengan stok fisik nol disembunyikan; total tetap benar.
            if ($qty === 0) {
                continue;
            }

            $batch = [
                'batch_no'   => $row['batch_no'],
                'expires_on' => $row['expires_on'],
                'quantity'   => $qty,
            ];

            // Kedaluwarsa jika expires_on < on_date (pada hari kedaluwarsa masih tersedia).
            if ($row['expires_on'] < $onDate) {
                $medicines[$id]['expired_batches'][] = $batch;
                $medicines[$id]['expired_quantity'] += $qty;
            } else {
                $medicines[$id]['available_batches'][] = $batch;
                $medicines[$id]['available_quantity'] += $qty;
            }
        }

        return ['on_date' => $onDate, 'medicines' => array_values($medicines)];
    }
}
