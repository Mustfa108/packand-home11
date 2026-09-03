<?php

namespace App\Http\Controllers\User;

use App\Enums\ActionItemStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActionPlan\UpdateActionPlanItemStatusRequest;
use App\Models\ActionPlanItem;
use Illuminate\Http\JsonResponse;

class ActionPlanItemController extends Controller
{
    /**
     * Update status of one action-plan item owned by the authenticated user.
     */
    public function updateStatus(UpdateActionPlanItemStatusRequest $request, int $id): JsonResponse
    {
        $item = ActionPlanItem::with('actionPlan.assessment')->findOrFail($id);
        $assessment = $item->actionPlan?->assessment;

        if (! $assessment || $request->user()->id !== $assessment->user_id) {
            return ApiResponse::error('غير مصرح لك بتعديل هذه المهمة.', 403);
        }

        $item->update([
            'status' => $request->validated('status'),
        ]);

        $item->refresh();
        $status = $item->status instanceof ActionItemStatus
            ? $item->status
            : ActionItemStatus::from((string) $item->status);

        return ApiResponse::success([
            'id' => $item->id,
            'status' => $status->value,
            'status_ar' => $status->labelAr(),
            'status_en' => $status->labelEn(),
        ], 'تم تحديث حالة المهمة بنجاح.');
    }
}
