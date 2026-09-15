<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramCandidateAccount extends Model
{
    protected $fillable = [
        'username',
        'discovered_via',
        'reason',
        'source_note',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];
}
