<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ProjectReview;
use App\Services\GeminiNlgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reviews = ProjectReview::where('user_id', $request->user()->id)
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (ProjectReview $review) => $this->summary($review))
            ->values();

        return ApiResponse::success($reviews, 'تم تحميل تقييمات المشاريع.');
    }

    public function store(Request $request, GeminiNlgService $ai): JsonResponse
    {
        $validated = $request->validate([
            'project_name' => ['required', 'string', 'max:160'],
            'stage' => ['required', 'in:idea,pilot,operating,growing'],
            'location' => ['nullable', 'string', 'max:160'],
            'team_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'annual_budget' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'mission' => ['required', 'string', 'min:20', 'max:2000'],
            'problem' => ['required', 'string', 'min:20', 'max:2000'],
            'beneficiaries' => ['required', 'string', 'min:10', 'max:1500'],
            'activities' => ['required', 'string', 'min:20', 'max:2000'],
            'impact' => ['nullable', 'string', 'max:1500'],
            'sustainability' => ['nullable', 'string', 'max:1500'],
        ], [
            'required' => 'هذا الحقل مطلوب.',
            'min' => 'أضف تفاصيل أكثر حتى تكون النتيجة واقعية.',
        ]);

        $evaluation = $ai->evaluateProject($validated);

        $review = DB::transaction(function () use ($request, $validated, $evaluation) {
            return ProjectReview::create([
                ...$validated,
                'user_id' => $request->user()->id,
                'ai_score' => $evaluation['score'],
                'ai_level' => $evaluation['level'],
                'ai_summary_ar' => $evaluation['summary'],
                'ai_strengths' => $evaluation['strengths'],
                'ai_risks' => $evaluation['risks'],
                'ai_recommendations' => $evaluation['recommendations'],
                'ai_kpis' => $evaluation['kpis'],
                'ai_generated_at' => now(),
            ]);
        });

        return ApiResponse::success($this->details($review), 'تم تحليل المشروع بنجاح.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $review = $this->ownedReview($request, $id);

        return ApiResponse::success($this->details($review), 'تم تحميل تقييم المشروع.');
    }

    public function chat(Request $request, int $id, GeminiNlgService $ai): JsonResponse
    {
        $review = $this->ownedReview($request, $id);
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1200'],
        ]);

        $history = $review->messages()
            ->latest()
            ->limit(8)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->all();

        $review->messages()->create([
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $answer = $ai->chatAboutProject($review, $validated['message'], $history);
        $review->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
        ]);

        return ApiResponse::success([
            'message' => $answer,
            'review_id' => $review->id,
        ], 'تمت الإجابة بواسطة مساعد المشروع.');
    }

    private function ownedReview(Request $request, int $id): ProjectReview
    {
        return ProjectReview::where('user_id', $request->user()->id)
            ->with('messages')
            ->findOrFail($id);
    }

    private function summary(ProjectReview $review): array
    {
        return [
            'id' => $review->id,
            'project_name' => $review->project_name,
            'stage' => $review->stage,
            'ai_score' => $review->ai_score,
            'ai_level' => $review->ai_level,
            'ai_summary_ar' => $review->ai_summary_ar,
            'created_at' => $review->created_at,
        ];
    }

    private function details(ProjectReview $review): array
    {
        return [
            ...$this->summary($review),
            'location' => $review->location,
            'team_size' => $review->team_size,
            'annual_budget' => $review->annual_budget,
            'mission' => $review->mission,
            'problem' => $review->problem,
            'beneficiaries' => $review->beneficiaries,
            'activities' => $review->activities,
            'impact' => $review->impact,
            'sustainability' => $review->sustainability,
            'strengths' => $review->ai_strengths ?? [],
            'risks' => $review->ai_risks ?? [],
            'recommendations' => $review->ai_recommendations ?? [],
            'kpis' => $review->ai_kpis ?? [],
            'messages' => $review->messages?->map(fn ($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at,
            ])->values() ?? [],
        ];
    }
}