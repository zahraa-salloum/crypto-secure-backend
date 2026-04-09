<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BannedEmail Model
 * Records email addresses that are permanently banned.
 */
class BannedEmail extends Model
{
    protected $fillable = ['email', 'banned_by', 'reason'];

    /**
     * The admin who issued the ban
     */
    public function bannedByUser()
    {
        return $this->belongsTo(User::class, 'banned_by');
    }
}
