<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Execution;
use App\Models\Skill;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
            ],
            'skills' => [
                'total' => Skill::count(),
                'active' => Skill::where('is_active', true)->count(),
            ],
            'workflows' => [
                'total' => Workflow::count(),
                'published' => Workflow::where('status', 'published')->count(),
            ],
            'executions' => [
                'total' => Execution::count(),
                'completed' => Execution::where('status', 'completed')->count(),
                'failed' => Execution::where('status', 'failed')->count(),
                'running' => Execution::where('status', 'running')->count(),
                'pending' => Execution::where('status', 'pending')->count(),
            ],
            'recent_executions' => Execution::with(['user:id,name', 'skill:id,name'])
                ->latest()
                ->take(10)
                ->get(['id', 'user_id', 'skill_id', 'type', 'status', 'created_at']),
            'executions_by_status' => Execution::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get(),
        ];

        return response()->json($stats);
    }
}
