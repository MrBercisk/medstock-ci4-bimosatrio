<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('users')->insertBatch([
            [
                'name'          => 'Petugas Penerimaan',
                'email'         => 'petugas@farmasi.test',
                'password_hash' => password_hash('Petugas#2026', PASSWORD_DEFAULT),
                'role'          => 'receiving_officer',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'name'          => 'Supervisor Farmasi',
                'email'         => 'supervisor@farmasi.test',
                'password_hash' => password_hash('Supervisor#2026', PASSWORD_DEFAULT),
                'role'          => 'pharmacy_supervisor',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ]);
    }
}
