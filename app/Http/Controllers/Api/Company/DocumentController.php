<?php



namespace App\Http\Controllers\Api\Company;



use App\Http\Controllers\Controller;

use App\Models\Notification;

use App\Models\VerificationDocument;

use App\Services\ActivityLogger;
use App\Services\FakeDocumentDetectionService;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;



class DocumentController extends Controller

{

    public function store(Request $request, FakeDocumentDetectionService $fakeDocumentDetector)

    {

        $company = $request->user()->company;



        $request->validate([

            'type' => ['nullable', 'in:secp,ntn,address'],

            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],

            'secp' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],

            'ntn' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],

            'address' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],

        ]);



        $files = [];



        if ($request->hasFile('file')) {

            if (! $request->filled('type')) {

                return response()->json([

                    'message' => 'Document type is required.',

                ], 422);

            }



            $files[$request->input('type')] = $request->file('file');

        }



        foreach (['secp', 'ntn', 'address'] as $type) {

            if ($request->hasFile($type)) {

                $files[$type] = $request->file($type);

            }

        }



        if ($files === []) {

            return response()->json([

                'message' => 'At least one document is required.',

            ], 422);

        }



        $uploaded = [];



        foreach ($files as $type => $file) {

            $fakeDocumentDetector->detectFakeDocument($file, $company->id, "company_{$type}_document");

            $path = $file->store("verification-documents/company-{$company->id}");



            $uploaded[] = VerificationDocument::updateOrCreate(

                [

                    'company_id' => $company->id,

                    'type' => $type,

                ],

                [

                    'file_path' => $path,

                    'status' => 'pending',

                ]

            );

        }



        $company->update([

            'status' => 'pending',

            'rejection_reason' => null,

        ]);



        ActivityLogger::log(

            'upload',

            'company_documents',

            "Company {$company->name} uploaded verification documents.",

            $request

        );



        Notification::create([

            'user_id' => $request->user()->id,

            'company_id' => $company->id,

            'type' => 'company_verification',

            'title' => 'Verification documents uploaded',

            'message' => 'Your company verification documents were uploaded and are pending admin review.',

        ]);



        return response()->json([

            'message' => 'Documents uploaded successfully.',

            'documents' => $uploaded,

        ]);

    }



    public function file(Request $request, VerificationDocument $document)

    {

        $company = $request->user()->company;



        if (! $company || $document->company_id !== $company->id) {

            return response()->json([

                'message' => 'You are not allowed to view this document.',

            ], 403);

        }



        if (! $document->file_path || ! Storage::disk('local')->exists($document->file_path)) {

            return response()->json([

                'message' => 'File not found.',

            ], 404);

        }



        ActivityLogger::log(

            'view_file',

            'secure_file_access',

            "Viewed own company verification document {$document->id}.",

            $request

        );



        return Storage::disk('local')->response($document->file_path);

    }

}
