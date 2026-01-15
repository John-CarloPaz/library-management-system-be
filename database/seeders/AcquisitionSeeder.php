<?php

namespace Database\Seeders;

use App\Models\Acquisition;
use Illuminate\Database\Seeder;

class AcquisitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed a few acquisitions; requested_by / requester id is 1 via procurement relation
        for ($i = 1; $i <= 5; $i++) {
            Acquisition::create([
                'title' => 'Seeded Acquisition ' . $i,
                'author' => 'Author ' . $i,
                'edition' => '1st',
                'isbn' => 'ACQ-ISBN-' . $i,
                'publisher' => 'Sample Publisher',
                'place_of_publication' => 'Sample City',
                'year_of_publication' => now()->year,
                'quantity_requested' => 5,
                'procurement_id' => null,
                'acquisition_method' => 'supplier',
                'supplier_name' => 'Default Supplier',
                'cost' => 500,
                'total_cost' => 2500,
                'branch_id' => 1,
                'date_acquired' => now()->toDateString(),
                'quantity_acquired' => 5,
                'acquisition_status' => 'pending',
                'received_by' => null,
                'is_archived' => false,
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]);
        }
    }
}
