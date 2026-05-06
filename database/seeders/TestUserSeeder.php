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

        // Link HR
        \App\Models\User::where('email','hr@test.com')
            ->update(['company_id'=>$c->id, 'hr_type'=>'hr_manager']);

        // Owner
        \App\Models\User::updateOrCreate(
            ['email'=>'owner@test.com'],
            [
                'name'=>'Test Owner',
                'password'=>bcrypt('123456'),
                'role'=>'company',
                'company_id'=>$c->id,
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
