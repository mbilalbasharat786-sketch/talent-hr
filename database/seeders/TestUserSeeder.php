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
    // Approve test company
    \App\Models\Company::where('email','company@test.com')
        ->update(['status'=>'approved','trust_level'=>'gold']);

    $c = \App\Models\Company::where('email','company@test.com')->first();

    if ($c) {
// Admin User (FIXED)
\App\Models\User::updateOrCreate(
    ['email'=>'admin@test.com'],
    [
        'name'=>'Test Admin',
        'password'=>bcrypt('123456'),
        'role'=>'hr',
        'company_id'=>$c->id,
        'hr_type'=>'admin_manager',
        'status'=>'active',
        'email_verified_at'=>now()
    ]
);
    // HR User (FIXED)
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
    }

    // Candidate
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
