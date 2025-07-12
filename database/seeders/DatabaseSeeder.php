<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Item;
use App\Enums\Role;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(10)->create();

        User::factory()->create([
            'name' => 'Joe',
            'email' => 'emailme@joelohr.com',
            'password' => bcrypt('password'),
            'role' => Role::PARENT,
        ]);

        Item::factory(100)->create();
    }
}
