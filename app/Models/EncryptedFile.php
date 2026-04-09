<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EncryptedFile Model
 * Represents an encrypted file uploaded by a user
 */
class EncryptedFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'files';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'original_filename',
        'encrypted_filename',
        'file_size',
        'mime_type',
        'algorithm',
        'share_token',
        'share_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'share_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the file
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if share link is valid
     *
     * @return bool
     */
    public function isShareValid(): bool
    {
        if (!$this->share_token) {
            return false;
        }

        if ($this->share_expires_at && $this->share_expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
