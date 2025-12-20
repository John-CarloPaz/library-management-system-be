<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acquisition extends Model
{
    protected $table = "acquisitions";

    protected $fillable = [
        "procurement_id",
        "title",
        "is_archived",
        "author",
        "edition",
        "isbn",
        "publisher",
        "place_of_publication",
        "year_of_publication",
        "quantity_requested",
        "acquisition_method",
        "supplier_name",
        "cost",
        'quantity_acquired',
        "date_acquired",
        "total_cost",
        "branch_id",
        "acquisition_status",
        "received_by",
        "created_by",
        "updated_by"
    ];

    public function procurement() {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }
    public function catalogue() {
        return $this->hasOne(Catalogue::class, 'acquisition_id');
    }
    public function branch() {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
