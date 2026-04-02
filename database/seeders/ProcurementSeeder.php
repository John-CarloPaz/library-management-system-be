<?php

namespace Database\Seeders;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $requestedByUserId = 2;
        $requester = User::query()->find($requestedByUserId);

        $actorName = $requester?->username ?: 'Seeder';

        $path = database_path('data/books.json');
        if (! File::exists($path)) {
            Log::warning('ProcurementSeeder: books.json not found', ['path' => $path]);
            return;
        }

        $raw = File::get($path);
        $rows = json_decode($raw, true);

        if (! is_array($rows)) {
            Log::warning('ProcurementSeeder: invalid JSON in books.json');
            return;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = $row['Title'] ?? null;
            $author = $row['Author'] ?? null;

            if (! $title || ! $author) {
                continue;
            }

            $year = $row['Year of Production'] ?? null;
            if (! is_numeric($year)) {
                $year = null;
            }

            Procurement::query()->create([
                'title' => (string) $title,
                'author' => (string) $author,
                'edition' => $row['Edition'] ?? null,
                'isbn' => $row['ISBN'] ?? null,
                'publisher' => $row['Publisher'] ?? null,
                'place_of_publication' => $row['Place of publication'] ?? null,
                'year_of_publication' => $year,
                'quantity_requested' => 1,
                'requested_by' => $requestedByUserId,
                'admin_approval' => 'pending',
                'is_archived' => false,
                'created_by' => $actorName,
                'updated_by' => $actorName,
            ]);
        }
    }
}
