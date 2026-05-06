<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Supervisor;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupervisorController extends Controller
{
    public function store(Request $request)
    {
        $company = $request->user()->company;

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('supervisors', 'email')->ignore(
                    optional($company->supervisors()->first())->id
                ),
            ],
            'cnic' => ['required', 'string', 'max:30'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $data = [
            'company_id' => $company->id,
            'name' => $request->name,
            'email' => $request->email,
            'cnic' => $request->cnic,
            'status' => 'pending',
            'rejection_reason' => null,
        ];

        if ($request->hasFile('selfie')) {
            $data['selfie_path'] = $request->file('selfie')->store("supervisor-selfies/company-{$company->id}");
        }

        $supervisor = Supervisor::updateOrCreate(
            ['company_id' => $company->id],
            $data
        );

        ActivityLogger::log(
            'upsert',
            'company_supervisor',
            "Company {$company->name} submitted supervisor {$supervisor->name}.",
            $request
        );

        return response()->json([
            'message' => 'Supervisor details submitted successfully.',
            'supervisor' => $supervisor,
        ]);
    }
}
