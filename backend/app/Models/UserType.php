<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserType extends Model
{
    use HasFactory, \App\Traits\LogsActivity;

    protected $table = 'user_type';

    public $timestamps = false;

    protected $fillable = ['name'];

    public function appUsers(): HasMany
    {
        return $this->hasMany(AppUser::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_type_permission');
    }
}
