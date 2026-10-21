<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TemporaryUpload extends Model
{
    use HasUuids;

    protected $fillable = [
        'session_id',
        'user_id',
        'file_path',
        'category',
        'page_count',
    ];
}
