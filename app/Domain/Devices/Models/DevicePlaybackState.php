<?php

namespace App\Domain\Devices\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['device_id', 'business_id', 'session_id', 'sequence', 'payload', 'received_at'])]
class DevicePlaybackState extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return ['device_id' => 'integer', 'business_id' => 'integer', 'payload' => 'array', 'sequence' => 'integer', 'received_at' => 'datetime'];
    }
}
