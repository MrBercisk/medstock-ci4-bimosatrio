<?php

namespace App\Models;

use CodeIgniter\Model;

class ReceiptLogModel extends Model
{
    protected $table         = 'receipt_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['receipt_id', 'user_id', 'action', 'created_at'];
    protected $useTimestamps = false;
}
