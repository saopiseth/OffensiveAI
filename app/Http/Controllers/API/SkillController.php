<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Skill\StoreSkillRequest;
use App\Http\Requests\Skill\UpdateSkillRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use App\Services\SkillConverterService;
use App\Services\SkillMirrorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SkillController extends Controller
{
    public function __construct(
        private readonly SkillMirrorService    $mirrorService,
        private readonly SkillConverterService $converterService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $skills = Skill::with(['creator:id,name', 'steps'])
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%"))
            ->when($request->category, fn($q, $c) => $q->where('category', $c))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($request->per_page ?? 15);

        return SkillResource::collection($skills);
    }

    public function store(StoreSkillRequest $request): JsonResponse
    {
        $skill = Skill::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(new SkillResource($skill->load('steps')), 201);
    }

    public function show(Skill $skill): JsonResponse
    {
        return response()->json(new SkillResource($skill->load(['steps', 'creator:id,name'])));
    }

    public function update(UpdateSkillRequest $request, Skill $skill): JsonResponse
    {
        $skill->update($request->validated());
        return response()->json(new SkillResource($skill->load('steps')));
    }

    public function destroy(Skill $skill): JsonResponse
    {
        $skill->delete();
        return response()->json(['message' => 'Skill deleted successfully']);
    }

    public function mirrorList(): JsonResponse
    {
        return response()->json(['data' => $this->mirrorService->listMirrorable()]);
    }

    public function mirrorPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids'       => 'required|array|min:1',
            'skill_ids.*'     => 'required|string',
            'target_provider' => 'required|in:openai',
            'target_model'    => 'required|string',
        ]);

        $preview = $this->mirrorService->preview(
            $validated['skill_ids'],
            $validated['target_provider'],
            $validated['target_model'],
        );

        return response()->json(['data' => $preview]);
    }

    public function mirrorImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids'       => 'required|array|min:1',
            'skill_ids.*'     => 'required|string',
            'target_provider' => 'required|in:openai',
            'target_model'    => 'required|string',
        ]);

        $results = $this->mirrorService->mirror(
            $validated['skill_ids'],
            $validated['target_provider'],
            $validated['target_model'],
            auth()->id(),
        );

        $created = collect($results)->where('status', 'created')->count();
        $skipped = collect($results)->where('status', 'skipped')->count();
        $failed  = collect($results)->where('status', 'failed')->count();

        return response()->json([
            'results' => $results,
            'summary' => compact('created', 'skipped', 'failed'),
        ]);
    }

    public function converterList(): JsonResponse
    {
        return response()->json(['data' => $this->converterService->listConvertible()]);
    }

    public function converterPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids'   => 'required|array|min:1',
            'skill_ids.*' => 'required|string',
        ]);

        return response()->json(['data' => $this->converterService->preview($validated['skill_ids'])]);
    }

    public function converterImport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_ids'   => 'required|array|min:1',
            'skill_ids.*' => 'required|string',
        ]);

        $results = $this->converterService->convert($validated['skill_ids'], auth()->id());

        $created = collect($results)->where('status', 'created')->count();
        $skipped = collect($results)->where('status', 'skipped')->count();
        $failed  = collect($results)->where('status', 'failed')->count();

        return response()->json([
            'results' => $results,
            'summary' => compact('created', 'skipped', 'failed'),
        ]);
    }

    public function duplicate(Skill $skill): JsonResponse
    {
        $newSkill = $skill->replicate();
        $newSkill->name = $skill->name . ' (Copy)';
        $newSkill->created_by = auth()->id();
        $newSkill->save();

        foreach ($skill->steps as $step) {
            $newStep = $step->replicate();
            $newStep->skill_id = $newSkill->id;
            $newStep->save();
        }

        return response()->json(new SkillResource($newSkill->load('steps')), 201);
    }
}
