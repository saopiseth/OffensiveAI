<?php

namespace App\Http\Controllers\API;

use App\Models\Workflow;
use App\Services\AptSimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AptSimulationController extends Controller
{
    public function __construct(private readonly AptSimulationService $service) {}

    /**
     * POST /api/apt-simulations
     * Generate a new APT simulation workflow.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'scenario_type' => 'required|string|min:5|max:200',
        ]);

        try {
            $result = $this->service->simulate(
                $request->input('scenario_type'),
                $request->user()?->id
            );

            $wf = $result['workflow'];

            return response()->json([
                'workflow' => [
                    'id'          => $wf->id,
                    'name'        => $wf->name,
                    'description' => $wf->description,
                    'status'      => $wf->status,
                    'node_count'  => count($wf->graph_data['nodes'] ?? []),
                    'edge_count'  => count($wf->graph_data['edges'] ?? []),
                    'created_at'  => $wf->created_at,
                ],
                'scenario'    => $result['scenario'],
                'advisory'    => $result['advisory'],
                'stages'      => $result['stages'],
                'tokens_used' => $result['tokens_used'],
                'duration_ms' => $result['duration_ms'],
            ], 201);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'APT simulation generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/apt-simulations
     * List all APT-generated workflows (have apt_meta key in graph_data).
     */
    public function index(Request $request): JsonResponse
    {
        $simulations = Workflow::whereNotNull('graph_data->apt_meta')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($simulations);
    }

    /**
     * GET /api/apt-simulations/presets
     * Return preset scenario types.
     */
    public function presets(): JsonResponse
    {
        return response()->json([
            'presets' => [
                ['value' => 'phishing campaign simulation',            'label' => 'Phishing Campaign',          'icon' => 'mail'],
                ['value' => 'ransomware infiltration simulation',       'label' => 'Ransomware Infiltration',     'icon' => 'lock'],
                ['value' => 'insider threat simulation',               'label' => 'Insider Threat',              'icon' => 'user-x'],
                ['value' => 'nation-state espionage simulation',       'label' => 'Nation-State Espionage',      'icon' => 'globe'],
                ['value' => 'compromised endpoint investigation',      'label' => 'Compromised Endpoint',        'icon' => 'monitor'],
                ['value' => 'supply chain attack simulation',          'label' => 'Supply Chain Attack',         'icon' => 'package'],
                ['value' => 'cloud infrastructure breach simulation',  'label' => 'Cloud Breach',                'icon' => 'cloud'],
                ['value' => 'financial sector APT simulation',        'label' => 'Financial Sector APT',        'icon' => 'landmark'],
            ],
        ]);
    }
}
