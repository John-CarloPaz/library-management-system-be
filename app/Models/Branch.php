<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'branches';
    protected $fillable = [
        'name',
        'address',
        'details',
        'public_ip',
        'is_archived',
        'is_main_branch',
        'created_by',
        'updated_by',
    ];

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function acquisitions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Acquisition::class, 'branch_id');
    }

    public function catalogues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Catalogue::class, 'branch_id');
    }

    public function books(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Book::class, 'branch_id');
    }
}
