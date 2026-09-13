<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'organization_name' => $user->organization_name,
            'org_type'          => $user->org_type,
            'org_size'          => $user->org_size,
            'team_member_count' => $user->team_member_count,
            'is_complete'       => $user->org_type !== null && $user->org_size !== null,
        ], 'تم تحميل بيانات المنظمة.');
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'organization_name' => ['sometimes', 'string', 'max:255'],
            'org_type'          => ['required', Rule::in(['civil_society', 'volunteer_team', 'startup', 'other'])],
            'org_size'          => ['required', Rule::in(['small', 'medium', 'large'])],
            'team_member_count' => [
                'nullable',
                'integer',
                'min:1',
                'max:100000',
                Rule::requiredIf(fn () => $request->org_type !== null && $this->sizeNeedsCount($request->org_size)),
            ],
        ], [
            'org_type.required'          => 'نوع المنظمة مطلوب.',
            'org_type.in'                => 'نوع المنظمة غير صحيح.',
            'org_size.required'          => 'حجم المنظمة مطلوب.',
            'org_size.in'                => 'حجم المنظمة غير صحيح.',
            'team_member_count.required' => 'عدد أعضاء الفريق مطلوب لتحديد حجم المنظمة.',
            'team_member_count.integer'  => 'عدد أعضاء الفريق يجب أن يكون رقماً صحيحاً.',
            'team_member_count.min'      => 'عدد أعضاء الفريق يجب أن يكون رقماً موجباً.',
        ]);

        $user->update($validated);

        return ApiResponse::success([
            'organization_name' => $user->organization_name,
            'org_type'          => $user->org_type,
            'org_type_ar'       => $user->org_type_ar,
            'org_size'          => $user->org_size,
            'org_size_ar'       => $user->org_size_ar,
            'team_member_count' => $user->team_member_count,
            'is_complete'       => true,
        ], 'تم تحديث بيانات المنظمة بنجاح.');
    }

    private function sizeNeedsCount(?string $size): bool
    {
        return $size === 'small' || $size === 'medium';
    }
}
