<?php

namespace Accredifysg\SingPassLogin\Models;

use Accredifysg\SingPassLogin\Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<User> where(string $column, string $operator = null, mixed $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder<User> first()
 * @method static \Illuminate\Database\Eloquent\Builder<User> find($id)
 * @method static \Illuminate\Database\Eloquent\Builder<User> create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<User> update(array<string, mixed> $attributes = [])
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'nric',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return UserFactory
     */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
