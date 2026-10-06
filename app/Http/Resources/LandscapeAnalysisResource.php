<?php

namespace App\Http\Resources;

use App\Models\Competitor;
use App\Models\LandscapeAnalysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A landscape with every subject id resolved to the company it names, as it
 * is called today. A competitor deleted since the run drops out of the rows
 * and the map rather than showing up as a stranger.
 *
 * @mixin LandscapeAnalysis
 */
class LandscapeAnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'report' => $this->report === null ? null : $this->resolved($this->report),
            'error' => $this->error,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function resolved(array $report): array
    {
        $companies = [LandscapeAnalysis::YOU => ['name' => $this->business->name, 'is_you' => true, 'competitor_id' => null]];

        foreach ($this->business->competitors as $competitor) {
            /** @var Competitor $competitor */
            $companies[LandscapeAnalysis::subjectFor($competitor)] = ['name' => $competitor->name, 'is_you' => false, 'competitor_id' => $competitor->id];
        }

        $resolve = fn (array $items): array => array_values(array_filter(array_map(
            fn (array $item): ?array => isset($companies[$item['subject']]) ? [...$item, ...$companies[$item['subject']]] : null,
            $items,
        )));

        return [
            ...$report,
            'rows' => $resolve($report['rows']),
            'map' => $report['map'] === null ? null : [...$report['map'], 'points' => $resolve($report['map']['points'])],
        ];
    }
}
