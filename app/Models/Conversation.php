<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Conversation Model
 * Represents an encrypted conversation between two users
 */
class Conversation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'algorithm',
        'last_message_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the first participant of the conversation
     */
    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * Get the second participant of the conversation
     */
    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * Get all messages in this conversation
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the other participant in the conversation
     *
     * @param int $userId
     * @return User|null
     */
    public function getOtherParticipant(int $userId)
    {
        if ($this->user_one_id === $userId) {
            return $this->userTwo;
        }

        if ($this->user_two_id === $userId) {
            return $this->userOne;
        }

        return null;
    }

    /**
     * Get unread message count for a specific user
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->messages()
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}
