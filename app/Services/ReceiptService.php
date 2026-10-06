<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Models\ReceiptItemModel;
use App\Models\ReceiptLogModel;
use App\Models\ReceiptModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RuntimeException;
use Throwable;

/**
 * Mengelola penerimaan obat (receipts) beserta item dan log aksinya.
 *
 * Alur create : prepare() (validasi bisnis) -> transaksi (receipt, items, log).
 * Alur read  : baseQuery() + itemsFor() / history().
 */
class ReceiptService
{
    private const TIMEZONE = 'Asia/Jakarta';

    private const LOG_CREATE = 'create';

    private BaseConnection $db;
    private ReceiptModel $receipts;
    private ReceiptItemModel $items;
    private ReceiptLogModel $logs;

    public function __construct()
    {
        // Koneksi dipakai bersama, sehingga satu transaksi mencakup
        // receipts, receipt_items, dan receipt_logs.
        $this->db       = db_connect();
        $this->receipts = new ReceiptModel();
        $this->items    = new ReceiptItemModel();
        $this->logs     = new ReceiptLogModel();
    }

    // Create

    /** Membuat penerimaan baru lengkap dengan item dan log; mengembalikan id penerimaan. */
    public function create(array $data, array $user): int
    {
        $payload = $this->prepare($data, null); // validasi bisnis dulu, sebelum menyentuh data

        $this->db->transBegin();

        try {
            $receiptId = $this->insertReceipt($payload, (int) $user['id']);

            $this->insertItems($receiptId, $payload['items']);
            $this->writeLog($receiptId, (int) $user['id'], self::LOG_CREATE);

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback(); // semua perubahan dibatalkan

            if ($this->isDuplicateEntry($e)) {
                throw new BusinessRuleException(
                    'Data duplikat: reference_no atau kombinasi obat dan batch sudah ada.'
                );
            }

            throw $e;
        }

        return $receiptId;
    }

    // Read


    /** Daftar semua penerimaan (terbaru dulu), masing-masing dengan item-nya. */
    public function all(): array
    {
        $rows = $this->baseQuery()
            ->orderBy('r.received_at', 'DESC')
            ->orderBy('r.id', 'DESC')
            ->get()->getResultArray();

        if ($rows === []) {
            return [];
        }

        $itemsByReceipt = $this->itemsFor(array_map('intval', array_column($rows, 'id')));

        return array_map(
            static fn (array $row) => $row + ['items' => $itemsByReceipt[(int) $row['id']] ?? []],
            $rows
        );
    }

    /** Detail satu penerimaan beserta item dan riwayat aksinya. */
    public function find(int $id): array
    {
        $row = $this->baseQuery()->where('r.id', $id)->get()->getRowArray();

        if ($row === null) {
            throw new NotFoundException('Penerimaan tidak ditemukan.');
        }

        $row['items']   = $this->itemsFor([$id])[$id] ?? [];
        $row['history'] = $this->history($id);

        return $row;
    }


    // Validasi bisnis 

    /**
     * Menormalkan input dan menjalankan seluruh aturan bisnis.
     * $ignoreReceiptId diisi saat update agar penerimaan itu tidak dianggap duplikat dirinya sendiri.
     *
     * @return array{reference_no:string, supplier_id:int, received_at:string, items:array}
     */
    private function prepare(array $data, ?int $ignoreReceiptId): array
    {
        $receivedAt   = $this->parseReceivedAt((string) $data['received_at']);
        $receivedDate = $receivedAt->format('Y-m-d');
        $referenceNo  = trim((string) $data['reference_no']);
        $supplierId   = (int) $data['supplier_id'];
        $items        = $this->normalizeItems($data['items']);

        $this->assertReferenceUnique($referenceNo, $ignoreReceiptId);
        $this->assertSupplierActive($supplierId);
        $this->assertMedicinesActive($items);
        $this->assertExpiryAfterReceivedDate($items, $receivedDate);
        $this->assertBatchExpiryConsistent($items, $ignoreReceiptId);

        return [
            'reference_no' => $referenceNo,
            'supplier_id'  => $supplierId,
            'received_at'  => $receivedAt->format('Y-m-d H:i:s'), // disimpan dalam waktu Jakarta
            'items'        => $items,
        ];
    }

    /** Mengurai received_at (ISO 8601 + zona waktu) dan mengonversinya ke waktu Jakarta. */
    private function parseReceivedAt(string $value): DateTimeImmutable
    {
        $invalid = new BusinessRuleException(
            'received_at harus berformat ISO 8601 lengkap dengan zona waktu, contoh 2026-10-03T10:00:00+07:00.'
        );

        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw $invalid;
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception) {
            throw $invalid;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw $invalid; // mis. tanggal 31 Februari
        }

        // Konversi ke Jakarta DULU, baru ambil tanggal kalendernya.
        return $date->setTimezone(new DateTimeZone(self::TIMEZONE));
    }

    /** Merapikan tipe/spasi tiap item dan menolak batch ganda dalam satu penerimaan. */
    private function normalizeItems(array $items): array
    {
        $normalized = [];
        $seenAt     = []; // batchKey => nomor item pertama yang memakainya

        foreach (array_values($items) as $index => $item) {
            $row = [
                'medicine_id' => (int) $item['medicine_id'],
                'batch_no'    => trim((string) $item['batch_no']),
                'expires_on'  => (string) $item['expires_on'],
                'quantity'    => (int) $item['quantity'],
            ];

            $key = $this->batchKey($row['medicine_id'], $row['batch_no']);

            if (isset($seenAt[$key])) {
                throw $this->itemError($index, sprintf(
                    'obat %d dengan batch %s sudah ada di item #%d. Satu batch hanya boleh sekali dalam satu penerimaan.',
                    $row['medicine_id'], $row['batch_no'], $seenAt[$key]
                ));
            }

            $seenAt[$key] = $index + 1;
            $normalized[] = $row;
        }

        return $normalized;
    }

    /** Memastikan reference_no belum dipakai penerimaan lain. */
    private function assertReferenceUnique(string $referenceNo, ?int $ignoreId): void
    {
        $query = $this->db->table('receipts')->where('reference_no', $referenceNo);

        if ($ignoreId !== null) {
            $query->where('id !=', $ignoreId); // nomor milik penerimaan sendiri bukan duplikat
        }

        if ($query->countAllResults() > 0) {
            throw new BusinessRuleException("reference_no {$referenceNo} sudah dipakai penerimaan lain.");
        }
    }

    /** Memastikan pemasok ada dan berstatus aktif. */
    private function assertSupplierActive(int $supplierId): void
    {
        $supplier = $this->db->table('suppliers')
            ->select('is_active')
            ->where('id', $supplierId)
            ->get()->getRowArray();

        if ($supplier === null) {
            throw new BusinessRuleException("Pemasok {$supplierId} tidak ditemukan.");
        }

        if ((int) $supplier['is_active'] !== 1) {
            throw new BusinessRuleException("Pemasok {$supplierId} tidak aktif.");
        }
    }

    /** Memastikan semua obat pada item ada dan berstatus aktif (satu query untuk semua). */
    private function assertMedicinesActive(array $items): void
    {
        $ids  = array_values(array_unique(array_column($items, 'medicine_id')));
        $rows = $this->db->table('medicines')
            ->select('id, is_active')
            ->whereIn('id', $ids)
            ->get()->getResultArray();

        $isActive = []; // medicine_id => bool
        foreach ($rows as $row) {
            $isActive[(int) $row['id']] = (int) $row['is_active'] === 1;
        }

        foreach ($items as $index => $item) {
            $id = $item['medicine_id'];

            if (! isset($isActive[$id])) {
                throw $this->itemError($index, "obat {$id} tidak ditemukan.");
            }

            if (! $isActive[$id]) {
                throw $this->itemError($index, "obat {$id} tidak aktif.");
            }
        }
    }

    /** Tanggal kedaluwarsa harus lebih akhir dari tanggal penerimaan (sama hari = ditolak). */
    private function assertExpiryAfterReceivedDate(array $items, string $receivedDate): void
    {
        foreach ($items as $index => $item) {
            // Format Y-m-d bisa dibandingkan langsung sebagai string.
            if ($item['expires_on'] <= $receivedDate) {
                throw $this->itemError($index, sprintf(
                    'tanggal kedaluwarsa %s harus lebih akhir dari tanggal penerimaan %s.',
                    $item['expires_on'], $receivedDate
                ));
            }
        }
    }

    /** Batch yang sama harus punya satu tanggal kedaluwarsa (dicek ke stok awal dan penerimaan LAIN). */
    private function assertBatchExpiryConsistent(array $items, ?int $ignoreReceiptId): void
    {
        $knownExpiry = $this->knownBatchExpiries(
            array_values(array_unique(array_column($items, 'medicine_id'))),
            $ignoreReceiptId
        );

        foreach ($items as $index => $item) {
            $key = $this->batchKey($item['medicine_id'], $item['batch_no']);

            if (isset($knownExpiry[$key]) && $knownExpiry[$key] !== $item['expires_on']) {
                throw $this->itemError($index, sprintf(
                    'batch %s untuk obat %d sudah tercatat dengan tanggal kedaluwarsa %s, bukan %s.',
                    $item['batch_no'], $item['medicine_id'], $knownExpiry[$key], $item['expires_on']
                ));
            }
        }
    }

    /**
     * Mengambil tanggal kedaluwarsa batch yang sudah tercatat (stok awal + penerimaan lain).
     *
     * @param  int[] $medicineIds
     * @return array<string, string> batchKey => expires_on
     */
    private function knownBatchExpiries(array $medicineIds, ?int $ignoreReceiptId): array
    {
        $in = implode(',', array_fill(0, count($medicineIds), '?')); // hanya tanda tanya, bukan input pengguna

        $sql = "SELECT medicine_id, batch_no, expires_on FROM seed_batch_stock WHERE medicine_id IN ($in)
                UNION ALL
                SELECT medicine_id, batch_no, expires_on FROM receipt_items
                 WHERE medicine_id IN ($in) AND receipt_id <> ?";

        $rows = $this->db
            ->query($sql, array_merge($medicineIds, $medicineIds, [$ignoreReceiptId ?? 0]))
            ->getResultArray();

        $known = [];
        foreach ($rows as $row) {
            // Data pertama yang ditemukan dianggap acuan.
            $known[$this->batchKey((int) $row['medicine_id'], $row['batch_no'])] ??= $row['expires_on'];
        }

        return $known;
    }

    /** Kunci unik batch. MySQL tidak membedakan huruf besar/kecil pada batch_no, jadi kuncinya disamakan. */
    private function batchKey(int $medicineId, string $batchNo): string
    {
        return $medicineId . '|' . mb_strtolower($batchNo);
    }

    /** Membuat exception validasi dengan awalan "Item #n:" (n dimulai dari 1). */
    private function itemError(int $index, string $message): BusinessRuleException
    {
        return new BusinessRuleException(sprintf('Item #%d: %s', $index + 1, $message));
    }

    // Penyimpanan

    /** Menyimpan header penerimaan; mengembalikan id baru. */
    private function insertReceipt(array $payload, int $userId): int
    {
        $id = $this->receipts->insert([
            'reference_no' => $payload['reference_no'],
            'supplier_id'  => $payload['supplier_id'],
            'received_at'  => $payload['received_at'],
            'created_by'   => $userId,
        ]);

        if ($id === false) {
            throw new RuntimeException('Gagal menyimpan penerimaan.');
        }

        return (int) $id;
    }

    /** Menyimpan semua item penerimaan sekaligus (batch insert). */
    private function insertItems(int $receiptId, array $items): void
    {
        $rows = array_map(static fn (array $item) => $item + ['receipt_id' => $receiptId], $items);

        if ($this->items->insertBatch($rows) === false) {
            throw new RuntimeException('Gagal menyimpan item penerimaan.');
        }
    }

    /** Mencatat aksi pengguna pada penerimaan ke tabel log. */
    private function writeLog(int $receiptId, int $userId, string $action): void
    {
        $id = $this->logs->insert([
            'receipt_id' => $receiptId,
            'user_id'    => $userId,
            'action'     => $action,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($id === false) {
            throw new RuntimeException('Gagal menyimpan log aksi.');
        }
    }

    /** Apakah error berasal dari pelanggaran unique key database. */
    private function isDuplicateEntry(Throwable $e): bool
    {
        return $e instanceof DatabaseException && str_contains($e->getMessage(), 'Duplicate entry');
    }

    // Query baca

    /** Query dasar penerimaan + nama pemasok, pembuat, dan pengubah terakhir. */
    private function baseQuery(): BaseBuilder
    {
        return $this->db->table('receipts r')
            ->select('r.*, s.name AS supplier_name, cu.name AS created_by_name, uu.name AS updated_by_name')
            ->join('suppliers s', 's.id = r.supplier_id')
            ->join('users cu', 'cu.id = r.created_by')
            ->join('users uu', 'uu.id = r.updated_by', 'left');
    }

    /**
     * Mengambil item untuk banyak penerimaan dalam satu query (tanpa N+1).
     *
     * @param  int[] $receiptIds
     * @return array<int, array> item dikelompokkan per receipt_id
     */
    private function itemsFor(array $receiptIds): array
    {
        $rows = $this->db->table('receipt_items items')
            ->select('items.receipt_id, items.medicine_id, med.code, med.name, med.unit, items.batch_no, items.expires_on, items.quantity')
            ->join('medicines med', 'med.id = items.medicine_id')
            ->whereIn('items.receipt_id', $receiptIds)
            ->orderBy('items.id')
            ->get()->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['receipt_id']][] = $row;
        }

        return $grouped;
    }

    /** Riwayat aksi satu penerimaan (urut dari yang paling lama). */
    private function history(int $receiptId): array
    {
        return $this->db->table('receipt_logs logs')
            ->select('logs.action, logs.user_id, u.name AS user_name, logs.created_at')
            ->join('users u', 'u.id = logs.user_id')
            ->where('logs.receipt_id', $receiptId)
            ->orderBy('logs.id')
            ->get()->getResultArray();
    }
}
