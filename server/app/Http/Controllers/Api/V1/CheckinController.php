<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateAgent;
use App\Http\Requests\Api\V1\CheckinRequest;
use App\Models\Host;
use App\Models\Snapshot;
use App\Services\Monitoring\CheckinProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckinController extends Controller
{
    public function __construct(private readonly CheckinProcessor $processor)
    {
    }

    public function __invoke(CheckinRequest $request): JsonResponse
    {
        /** @var Host $host */
        $host = $request->attributes->get(AuthenticateAgent::REQUEST_ATTRIBUTE);

        $payload = $request->validated();
        $collectedAt = CarbonImmutable::parse($payload['collected_at']);

        $snapshot = $this->processor->process($host, $payload, $collectedAt);

        return response()->json([
            'snapshot_id' => $snapshot->id,
            'host_status' => $host->refresh()->status,
        ], Response::HTTP_ACCEPTED);
    }
}
