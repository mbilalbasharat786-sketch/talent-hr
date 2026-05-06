<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
public function run()
{
    // ✅ ALWAYS create company
    $c = \App\Models\Company::updateOrCreate(
        ['email' => 'company@test.com'],
        [
            'name' => 'Test Company',
            'status' => 'approved',
            'trust_level' => 'gold'
        ]
    );
// ✅ owner user (no condition now)
    \App\Models\User::updateOrCreate(
        ['email'=>'owner@test.com'],
        [
            'name'=>'Test Owner',
            'password'=>bcrypt('123456'),
            'role'=>'hr',
            'company_id'=>$c->id,
            'owner_type'=>'owner_manager',
            'status'=>'active',
            'email_verified_at'=>now()
        ]
    );

    // ✅ HR user (no condition now)
    \App\Models\User::updateOrCreate(
        ['email'=>'hr@test.com'],
        [
            'name'=>'Test HR',
            'password'=>bcrypt('123456'),
            'role'=>'hr',
            'company_id'=>$c->id,
            'hr_type'=>'hr_manager',
            'status'=>'active',
            'email_verified_at'=>now()
        ]
    );

    // ✅ Candidate
    \App\Models\User::updateOrCreate(
        ['email'=>'candidate@test.com'],
        [
            'name'=>'Test Candidate',
            'password'=>bcrypt('123456'),
            'role'=>'candidate',
            'status'=>'active',
            'email_verified_at'=>now()
        ]
    );
}
}
