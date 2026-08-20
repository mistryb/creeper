<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\CompleteCreepRun;
use App\Creeping\Exceptions\InvalidCreepPayload;
use App\Enums\RunStatus;
use App\Http\Controllers\Controller;
use App\Models\CreepRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Where an asynchronous agent posts its results.
 *
 * The URL is signed and expiring — it's handed to the agent when the run
 * starts and is the only thing that authenticates the response.
 */
class CreepCallbackController extends Controller
{
    public function __invoke(Request $request, CreepRun $run, CompleteCreepRun $completeRun): JsonResponse
    {
        // Agents retry. A run that already has an answer keeps it.
        if ($run->status->isFinished()) {
            return response()->json([
                'message' => 'This run has already finished.',
            ], 409);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        if (($payload['status'] ?? null) === 'failed') {
            $error = $payload['error'] ?? null;

            $run->finish(RunStatus::Failed, is_string($error) ? $error : 'The agent reported a failure.');

            return response()->json(['message' => 'Recorded as failed.']);
        }

        try {
            $completeRun->handle($run, $payload);
        } catch (InvalidCreepPayload $exception) {
            $run->finish(RunStatus::Failed, $exception->getMessage());

            Log::warning('A creep callback sent an unusable payload.', [
                'creep_run_id' => $run->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Thanks.']);
    }
}
