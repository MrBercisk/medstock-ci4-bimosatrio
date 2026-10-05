<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReceiptItems extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'receipt_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'medicine_id' => ['type' => 'INT', 'unsigned' => true],
            'batch_no'    => ['type' => 'VARCHAR', 'constraint' => 50],
            'expires_on'  => ['type' => 'DATE'],
            'quantity'    => ['type' => 'INT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['receipt_id', 'medicine_id', 'batch_no'], 'uq_receipt_items_batch');
        $this->forge->addKey(['medicine_id', 'batch_no'], false, false, 'idx_receipt_items_batch');
        $this->forge->addForeignKey('receipt_id', 'receipts', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('receipt_items', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('receipt_items', true);
    }
}
