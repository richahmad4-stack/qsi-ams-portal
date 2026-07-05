<?php

namespace App\Models;

use CodeIgniter\Model;

class FoodChainCategoryModel extends Model
{
    protected $table = 'food_chain_categories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'code',
        'title',
        'description',
        'active',
    ];
    protected $useTimestamps = false;
}
