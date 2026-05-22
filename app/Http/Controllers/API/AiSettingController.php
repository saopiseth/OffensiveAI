<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiSettingResource;
use App\Models\AiSetting;
use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSettingController extends Controller
{
    public function __construct(private AiProviderService $aiProviderService) {}

    public function index(): JsonResponse
    {
        return response()->json(AiSettingResource::collection(AiSetting::all()));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:claude,openai|unique:ai_settings,provider',
            'api_key' => 'required|string',
            'default_model' => 'required|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:100000',
        ]);

        $setting = AiSetting::create($request->all());
        return response()->json(new AiSettingResource($setting), 201);
    }

    public function show(AiSetting $aiSetting): JsonResponse
    {
        return response()->json(new AiSettingResource($aiSetting));
    }

    public function update(Request $request, AiSetting $aiSetting): JsonResponse
    {
        $request->validate([
            'api_key' => 'nullable|string',
            'default_model' => 'nullable|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1|max:100000',
        ]);

        $data = $request->all();
        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $aiSetting->update($data);
        return response()->json(new AiSettingResource($aiSetting));
    }

    public function destroy(AiSetting $aiSetting): JsonResponse
    {
        $aiSetting->delete();
        return response()->json(['message' => 'AI setting deleted successfully']);
    }

    public function test(Request $request, AiSetting $aiSetting): JsonResponse
    {
        $result = $this->aiProviderService->test($aiSetting);
        return response()->json($result);
    }

    public function activate(AiSetting $aiSetting): JsonResponse
    {
        AiSetting::where('id', '!=', $aiSetting->id)->update(['is_active' => false]);
        $aiSetting->update(['is_active' => true]);
        return response()->json(new AiSettingResource($aiSetting));
    }
}
