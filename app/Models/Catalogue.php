<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Catalogue extends Model
{
    protected $table = "catalogues";

    protected $fillable = [
        "acquisition_id",
        "number_of_copies",
        "dewey",
        "cutter_number",
        "call_number",
        "title",
        "author",
        "edition",
        "isbn",
        "publisher",
        "place_of_publication",
        "year_of_publication",
        "cataloging_status",
        "is_provisional",
        "created_by",
        "updated_by"
    ];
    public function acquisition() {
        return $this->belongsTo(Acquisition::class, 'acquisition_id');
    }
    public function books() {
        return $this->hasMany(Book::class, 'catalogue_id');
    }
}
