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
        "qr_code"
    ];
    public function catalgoue() {
        return $this->belongsTo(Catalogue::class, 'acquisition_id');
    }
}
