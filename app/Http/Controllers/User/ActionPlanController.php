<?php

namespace App\Http\Controllers\User;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionPlanController extends Controller
{
    // Action plan data is returned as part of AssessmentController::results()
    // This controller exists for potential future standalone endpoints
}
