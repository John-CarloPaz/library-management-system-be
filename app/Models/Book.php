<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $table = "books";

    protected $fillable = [
        "catalogue_id",
        "copy_number",
        "reference_number",
        "qr_code",
        "is_archived",
        "created_by",
        "updated_by",
        "expiration_date",
        "branch_id",
        "book_status",
        "is_archived"
    ];
    public function catalogue()
    {
        return $this->belongsTo(Catalogue::class, 'catalogue_id');
    }
    public function borrows()
    {
        return $this->hasMany(Borrow::class, 'book_id');
    }

    public function branch() {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
