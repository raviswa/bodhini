<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nnjeim\World\Actions\SeedAction;

class WorldSeeder extends Seeder
{
    public function run(): void
    {
        // SeedAction wipes the catalog in its constructor, then inserts every
        // country one at a time. Leave a finished catalog alone.
        if ($this->catalogIsLoaded()) {
            return;
        }

        $this->call([
            SeedAction::class,
        ]);
    }

    private function catalogIsLoaded(): bool
    {
        return Schema::hasTable('countries')
            && Schema::hasTable('states')
            && DB::table('countries')->exists()
            && DB::table('states')->exists();
    }
}
