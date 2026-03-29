<?php

namespace Database\Seeders;

use App\Models\FetNet\ClientLevel;
use Illuminate\Database\Seeder;

class ClientLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['code' => 'CLU', 'level' => 'Cluster'],
            ['code' => 'FAK', 'level' => 'Fakultas'],
            ['code' => 'PRG', 'level' => 'Program Studi'],
        ];

        foreach ($levels as $level) {
            ClientLevel::firstOrCreate(['code' => $level['code']], $level);
        }
    }
}
