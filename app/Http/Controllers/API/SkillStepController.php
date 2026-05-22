<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SkillStep\StoreSkillStepRequest;
use App\Http\Requests\SkillStep\UpdateSkillStepRequest;
use App\Http\Resources\SkillStepResource;
use App\Models\Skill;
use App\Models\SkillStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillStepController extends Controller
{
    public function index(Skill $skill): JsonResponse
    {
        return response()->json(SkillStepResource::collection($skill->steps));
    }

    public function store(StoreSkillStepRequest $request, Skill $skill): JsonResponse
    {
        $lastOrder = $skill->steps()->max('execution_order') ?? -1;

        $step = $skill->steps()->create([
            ...$request->validated(),
            'execution_order' => $lastOrder + 1,
        ]);

        return response()->json(new SkillStepResource($step), 201);
    }

    public function show(SkillStep $step): JsonResponse
    {
        return response()->json(new SkillStepResource($step));
    }

    public function update(UpdateSkillStepRequest $request, SkillStep $step): JsonResponse
    {
        $step->update($request->validated());
        return response()->json(new SkillStepResource($step));
    }

    public function destroy(SkillStep $step): JsonResponse
    {
        $step->delete();
        return response()->json(['message' => 'Step deleted successfully']);
    }

    public function reorder(Request $request, Skill $skill): JsonResponse
    {
        $request->validate(['steps' => 'required|array', 'steps.*.id' => 'required', 'steps.*.order' => 'required|integer']);

        foreach ($request->steps as $item) {
            SkillStep::where('id', $item['id'])->where('skill_id', $skill->id)->update(['execution_order' => $item['order']]);
        }

        return response()->json(['message' => 'Steps reordered successfully']);
    }
}
