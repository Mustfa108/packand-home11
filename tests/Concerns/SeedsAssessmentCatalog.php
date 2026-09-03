<?php

namespace Tests\Concerns;

use App\Models\Question;
use Database\Seeders\PillarSeeder;
use Database\Seeders\QuestionSeeder;

trait SeedsAssessmentCatalog
{
    protected function seedCatalog(): void
    {
        $this->seed(PillarSeeder::class);
        $this->seed(QuestionSeeder::class);
    }

    /**
     * @return array<int, array{question_id: int, score: int}>
     */
    protected function answersWithScore(int $score): array
    {
        return Question::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Question $question) => [
                'question_id' => $question->id,
                'score' => $score,
            ])
            ->all();
    }

    /**
     * Apply per-pillar scores: [pillar_key => score 1-5]
     *
     * @param  array<string, int>  $pillarScores
     * @return array<int, array{question_id: int, score: int}>
     */
    protected function answersByPillar(array $pillarScores): array
    {
        return Question::query()
            ->with('pillar')
            ->orderBy('id')
            ->get()
            ->map(fn (Question $question) => [
                'question_id' => $question->id,
                'score' => $pillarScores[$question->pillar->key] ?? 3,
            ])
            ->all();
    }
}
