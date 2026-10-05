<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReceiptLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'receipt_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'action'     => ['type' => 'ENUM', 'constraint' => ['create', 'update']],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['receipt_id', 'created_at']);
        $this->forge->addForeignKey('receipt_id', 'receipts', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('receipt_logs', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('receipt_logs', true);
    }
}
