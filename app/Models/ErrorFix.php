<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorFix extends Model
{
    protected $fillable = [
        'error_hash',
        'error_message',
        'error_context',
        'fix_description',
        'fix_command',
        'fix_type',
        'fix_applied',
        'fix_result',
    ];

    protected $casts = [
        'fix_applied' => 'boolean',
    ];
}
