<?php

namespace Database\Seeders;

use App\Models\Catalogue;
use Illuminate\Database\Seeder;

class CatalogueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed at least 5 pending catalogues
        for ($i = 1; $i <= 5; $i++) {
            Catalogue::create([
                'acquisition_id' => null,
                'number_of_copies' => 3,
                'dewey' => '000.' . $i,
                'cutter_number' => 'C' . $i,
                'call_number' => 'CALL-' . $i,
                'title' => 'Pending Catalogue ' . $i,
                'author' => 'Author ' . $i,
                'edition' => '1st',
                'isbn' => 'ISBN-' . $i,
                'publisher' => 'Sample Publisher',
                'place_of_publication' => 'Sample City',
                'year_of_publication' => now()->year,
                'is_provisional' => false,
                'is_archived' => false,
                'branch_id' => 1,
                'cataloging_status' => 'pending',
                'created_by' => 'Seeder',
                'updated_by' => 'Seeder',
            ]);
        }
    }
}
