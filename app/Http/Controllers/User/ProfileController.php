<?php

namespace App\Http\Controllers\User;

use App\Enums\UserTheme;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Return saved locale and theme for the authenticated user.
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'locale' => $user->locale ?? 'ar',
            'theme' => $user->theme ?? UserTheme::SYSTEM->value,
        ], 'تم تحميل التفضيلات.');
    }

    /**
     * Persist locale and/or theme. Frontend owns visual application of theme.
     */
    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->fill($data)->save();

        return ApiResponse::success([
            'locale' => $user->locale,
            'theme' => $user->theme,
        ], 'تم حفظ التفضيلات بنجاح.');
    }
}
