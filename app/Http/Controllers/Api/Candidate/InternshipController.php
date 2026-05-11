<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use App\Models\Internship;
use App\Models\Notification;
use App\Services\ActivityLogger;
use App\Services\DuplicateCertificateDetectionService;
use App\Services\FakeDocumentDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InternshipController extends Controller
{
    public function index(Request $request)
    {
        $internships = $request->user()->internships()
            ->latest()
            ->paginate(20);

        return response()->json($internships);
    }

    public function store(
        Request $request,
        DuplicateCertificateDetectionService $duplicateDetector,
        FakeDocumentDetectionService $fakeDocumentDetector
    )
    {
        $candidate = $request->user();

        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'duration' => ['required', 'string', 'max:255'],
            'supervisor_email' => ['required', 'email'],
            'certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $certificate = $request->file('certificate');
        $path = $certificate->store('internship-certificates');
        $certificateHash = hash_file('sha256', $certificate->getPathname());
        $certificateText = trim($request->company_name.' '.$request->duration.' '.$request->supervisor_email);

        $duplicateDetector->detectDuplicateCertificate($certificate, $candidate->id, [
            'company_name' => $request->company_name,
            'duration' => $request->duration,
            'supervisor_email' => $request->supervisor_email,
        ]);

        $metadataMatches = $duplicateDetector->checkMetadataSimilarity([
            'company_name' => $request->company_name,
            'duration' => $request->duration,
            'supervisor_email' => $request->supervisor_email,
        ], $candidate->id);

        if ($metadataMatches !== []) {
            \App\Models\FraudLog::create([
                'type' => 'duplicate_internship_certificate',
                'reference_id' => $candidate->id,
                'description' => 'Duplicate internship metadata detected. Matches: '.json_encode($metadataMatches),
                'status' => 'open',
            ]);
        }

        $fakeDocumentDetector->detectFakeDocument($certificate, $candidate->id, 'internship_certificate');

        $internship = Internship::create([
            'candidate_id' => $candidate->id,
            'company_name' => $request->company_name,
            'duration' => $request->duration,
            'supervisor_email' => $request->supervisor_email,
            'certificate_path' => $path,
            'certificate_hash' => $certificateHash,
            'certificate_text' => $certificateText,
            'status' => 'pending',
        ]);

        Mail::raw(
            "Please verify internship submission for {$candidate->name} at {$request->company_name}.",
            fn ($message) => $message->to($request->supervisor_email)->subject('Internship Verification Request')
        );

        Notification::create([
            'user_id' => $candidate->id,
            'type' => 'system_alert',
            'title' => 'Internship submitted',
            'message' => "Internship proof for {$request->company_name} submitted for verification.",
        ]);

        ActivityLogger::log(
            'submit_internship',
            'candidate_internships',
            "Candidate {$candidate->email} submitted internship {$internship->id}.",
            $request,
            $candidate->id
        );

        return response()->json([
            'message' => 'Internship submitted successfully.',
            'internship' => $internship,
        ], 201);
    }
}
