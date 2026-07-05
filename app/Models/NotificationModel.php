<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'tenant_id',
        'user_id',
        'notification_rule_id',
        'title',
        'body',
        'channel',
        'related_module',
        'related_id',
        'status',
        'sent_at',
        'read_at',
    ];
}
