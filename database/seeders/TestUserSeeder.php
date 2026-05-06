<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run()
    {
        // ✅ Company create
        $company = \App\Models\Company::updateOrCreate(
            ['email' => 'company@test.com'],
            [
                'name' => 'Test Company',
                'status' => 'approved',
                'trust_level' => 'gold'
            ]
        );

        // ✅ HR User
        \App\Models\User::updateOrCreate(
            ['email'=>'hr@test.com'],
            [
                'name'=>'Test HR',
                'password'=>bcrypt('123456'),
                'role'=>'hr',
                'company_id'=>$company->id,
                'hr_type'=>'hr_manager',
                'status'=>'active',
                'email_verified_at'=>now()
            ]
        );

        // ✅ Candidate User
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

        // ✅ Company Owner (for /company/login)
        \App\Models\User::updateOrCreate(
            ['email'=>'owner@test.com'],
            [
                'name'=>'Test Owner',
                'password'=>bcrypt('123456'),
                'role'=>'company',
                'company_id'=>$company->id,
                'status'=>'active',
                'email_verified_at'=>now()
            ]
        );

        // ✅ Admin User (for /admin/login)
        \App\Models\User::updateOrCreate(
            ['email'=>'admin@test.com'],
            [
                'name'=>'Test Admin',
                'password'=>bcrypt('123456'),
                'role'=>'admin',
                'status'=>'active',
                'email_verified_at'=>now()
            ]
        );
    }
}
