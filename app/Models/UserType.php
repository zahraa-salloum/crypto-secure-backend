<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UserType Model
 * Represents user roles: 1=admin, 2=user
 */
class UserType extends Model
{
    public const ADMIN = 1;
    public const USER  = 2;

    protected $fillable = ['name'];

    /**
     * Users that belong to this type
     */
    public function users()
    {
        return $this->hasMany(User::class, 'user_type_id');
    }
}
