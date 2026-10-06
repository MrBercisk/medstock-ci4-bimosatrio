<?php

namespace App\Models;

use CodeIgniter\Model;

class ReceiptModel extends Model
{
    protected $table         = 'receipts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['reference_no', 'supplier_id', 'received_at', 'created_by', 'updated_by'];
    protected $useTimestamps = true;
}
