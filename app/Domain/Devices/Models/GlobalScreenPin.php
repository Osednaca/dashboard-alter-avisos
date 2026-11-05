<?php

namespace App\Domain\Devices\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Hidden(['pin_hash'])]
class GlobalScreenPin extends Model
{
    public $incrementing = false;

    public static function current(): ?self
    {
        return static::query()->find(1);
    }
}
