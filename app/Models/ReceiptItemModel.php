<?php

namespace App\Models;

use CodeIgniter\Model;

class ReceiptItemModel extends Model
{
    protected $table         = 'receipt_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['receipt_id', 'medicine_id', 'batch_no', 'expires_on', 'quantity'];
    protected $useTimestamps = false;
}
