<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReceipts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'reference_no'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'supplier_id'   => ['type' => 'INT', 'unsigned' => true],
            'received_at'   => ['type' => 'DATETIME'],
            'created_by'    => ['type' => 'BIGINT', 'unsigned' => true],
            'updated_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('reference_no');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('updated_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('receipts', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('receipts', true);
    }
}
