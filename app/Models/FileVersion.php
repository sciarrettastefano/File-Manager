<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileVersion extends Model
{
    protected $tabel = 'file_versions';

    protected $fillable = [
        'file_id',
        'version_number',
        'hash',
        'created_at',
        'updated_at'
    ];
}
