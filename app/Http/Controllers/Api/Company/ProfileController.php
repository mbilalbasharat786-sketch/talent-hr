<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $company = $request->user()->company()->with([
            'verificationDocuments',
            'supervisors',
        ])->first();

        return response()->json([
            'company' => $company,
        ]);
    }

    public function update(Request $request)
    {
        $company = $request->user()->company;

        $request->validate([
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cover_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'about' => ['nullable', 'string'],
            'industry' => ['nullable', 'string', 'max:255'],
            'company_size' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'office_locations' => ['nullable'],
            'working_hours' => ['nullable'],
        ]);

        $data = $request->only([
            'about',
            'industry',
            'company_size',
            'website',
            'office_locations',
            'working_hours',
        ]);

        if ($request->hasFile('logo')) {
          $path = $request->file('logo')->store('company-logos', 'public');
    $data['logo'] = Storage::disk('public')->url($path);
        }

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('company-cover-images', 'public');
            $data['cover_image'] = Storage::disk('public')->url($path);
        }

        if ($request->has('office_locations') && is_string($request->office_locations)) {
            $data['office_locations'] = json_decode($request->office_locations, true);
        }

        if ($request->has('working_hours') && is_string($request->working_hours)) {
            $data['working_hours'] = json_decode($request->working_hours, true);
        }

        $company->update($data);

        ActivityLogger::log(
            'update',
            'company_profile',
            "Company {$company->name} profile updated.",
            $request
        );

        return response()->json([
            'message' => 'Company profile updated successfully.',
            'company' => $company->fresh(),
        ]);
    }
}

