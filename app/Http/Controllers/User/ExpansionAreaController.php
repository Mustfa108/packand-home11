<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpansionArea\StoreExpansionAreaRequest;
use App\Http\Requests\ExpansionArea\UpdateExpansionAreaRequest;
use App\Models\ExpansionArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpansionAreaController extends Controller
{
    /**
     * List expansion areas owned by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $areas = ExpansionArea::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ExpansionArea $area) => $this->mapArea($area));

        return ApiResponse::success(['items' => $areas], 'تم تحميل مناطق التوسع.');
    }

    /**
     * Create an expansion area pin for the authenticated user.
     */
    public function store(StoreExpansionAreaRequest $request): JsonResponse
    {
        try {
            $area = DB::transaction(function () use ($request) {
                return ExpansionArea::create([
                    ...$request->validated(),
                    'user_id' => $request->user()->id,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Expansion area create failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return ApiResponse::success($this->mapArea($area), 'تم إضافة منطقة التوسع.', 201);
    }

    /**
     * Update an owned expansion area.
     */
    public function update(UpdateExpansionAreaRequest $request, int $id): JsonResponse
    {
        $area = ExpansionArea::findOrFail($id);

        if ($area->user_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك بتعديل هذه المنطقة.', 403);
        }

        try {
            DB::transaction(function () use ($area, $request) {
                $area->update($request->validated());
            });
        } catch (Throwable $e) {
            Log::error('Expansion area update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return ApiResponse::success($this->mapArea($area->fresh()), 'تم تحديث منطقة التوسع.');
    }

    /**
     * Delete an owned expansion area.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $area = ExpansionArea::findOrFail($id);

        if ($area->user_id !== $request->user()->id) {
            return ApiResponse::error('غير مصرح لك بحذف هذه المنطقة.', 403);
        }

        $area->delete();

        return ApiResponse::success(null, 'تم حذف منطقة التوسع.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapArea(ExpansionArea $area): array
    {
        return [
            'id' => $area->id,
            'name_ar' => $area->name_ar,
            'name_en' => $area->name_en,
            'lat' => (float) $area->lat,
            'lng' => (float) $area->lng,
            'notes' => $area->notes,
            'created_at' => $area->created_at,
            'updated_at' => $area->updated_at,
        ];
    }
}
