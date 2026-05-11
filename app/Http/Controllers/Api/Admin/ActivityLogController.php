<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user:id,name,email,role')
            ->when($request->user_id, function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when($request->user, function ($query) use ($request) {
                $term = $request->user;
                $query->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('email', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%");
                });
            })
            ->when($request->module, function ($query) use ($request) {
                $query->where('module', $request->module);
            })
            ->when($request->date, function ($query) use ($request) {
                $query->whereDate('created_at', $request->date);
            })
            ->latest()
            ->paginate(20);

        return response()->json($logs);
    }
}
