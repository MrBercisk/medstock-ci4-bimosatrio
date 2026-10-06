<?php

namespace App\Policies;

class ReceiptPolicy
{
    private const ROLE_SUPERVISOR = 'pharmacy_supervisor';

    /**
     * Supervisor boleh mengubah semua penerimaan.
     * Petugas hanya boleh mengubah penerimaan yang ia buat sendiri.
     * $user berasal dari sesi (server), bukan dari body request.
     */
    public function canUpdate(array $user, array $receipt): bool
    {
        return $user['role'] === self::ROLE_SUPERVISOR
            || (int) $receipt['created_by'] === (int) $user['id'];
    }
}
