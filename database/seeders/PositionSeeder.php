<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Position;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        // Add Adviser first
        Position::create(['position_name' => 'Adviser']);

        // Other positions
        Position::create(['position_name' => 'President']);
        Position::create(['position_name' => 'Vice President']);
        Position::create(['position_name' => 'Secretary']);
        Position::create(['position_name' => 'Treasurer']);
    }
}