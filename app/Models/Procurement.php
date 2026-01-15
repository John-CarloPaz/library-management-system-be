<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procurement extends Model
{
    protected $table = "procurements";

    protected $fillable = [
      "title",
      "author",
      "edition",
      "isbn",
      "publisher",
      "place_of_publication",
      "year_of_publication",
      "quantity_requested",
      "dean_approval",
      "admin_approval",
      "department_id",
      "requested_by",
      "created_by",
      "is_archived",
      "updated_by",
    ];

    public function acquisitions()
    {
        return $this->belongsTo(Acquisition::class, 'requested_by');
    }
}
