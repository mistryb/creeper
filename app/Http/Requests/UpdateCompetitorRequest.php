<?php

namespace App\Http\Requests;

use App\Concerns\CompetitorValidationRules;
use App\Models\Competitor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompetitorRequest extends FormRequest
{
    use CompetitorValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Competitor $competitor */
        $competitor = $this->route('competitor');

        return $this->competitorRules($competitor->business_id, $competitor->id);
    }
}
