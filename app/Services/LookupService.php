<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Data referensi (pemasok dan obat aktif) untuk pilihan di form penerimaan. */
class LookupService
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /** Pemasok aktif, urut nama. */
    public function activeSuppliers(): array
    {
        $rows = $this->db->table('suppliers')
            ->select('id, name')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()->getResultArray();

        return array_map(static fn (array $r) => [
            'id'   => (int) $r['id'],
            'name' => $r['name'],
        ], $rows);
    }

    /** Obat aktif, urut kode. */
    public function activeMedicines(): array
    {
        $rows = $this->db->table('medicines')
            ->select('id, code, name, unit')
            ->where('is_active', 1)
            ->orderBy('code')
            ->get()->getResultArray();

        return array_map(static fn (array $r) => [
            'id'   => (int) $r['id'],
            'code' => $r['code'],
            'name' => $r['name'],
            'unit' => $r['unit'],
        ], $rows);
    }
    /* semua batch tercatat (stok awal +penerimaan) */
    public function getBatches(): array
    {
        $rows = $this->db->query(
            'SELECT medicine_id, batch_no, expires_on FROM seed_batch_stock 
             UNION
            SELECT medicine_id, batch_no, expires_on FROM receipt_items
            ORDER BY medicine_id, batch_no
            '
        )->getResultArray();
        
         return array_map(static fn (array $r) => [
            'medicine_id' => (int) $r['medicine_id'],
            'batch_no'    => $r['batch_no'],
            'expires_on'  => $r['expires_on'],
        ], $rows);
    }
}
