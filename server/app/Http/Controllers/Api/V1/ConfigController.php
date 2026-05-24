<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Interval is currently fixed at 30 s; if it becomes user-configurable
        // it should move into SettingsRepository.
        return response()->json([
            'interval_seconds' => 30,
            'collectors' => [
                'system' => ['enabled' => true],
            ],
        ]);
    }
}
