<?php

namespace App\Ai\Contracts;

use App\Models\BusinessAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Something a business analysis can be run on: the user's own business today,
 * and a competitor once there are competitors.
 */
interface Analyzable
{
    /**
     * Every analysis run on this subject, oldest first.
     *
     * @return MorphMany<BusinessAnalysis, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function analyses(): MorphMany;

    /**
     * The account that owns this subject and pays for analysing it.
     */
    public function owner(): User;

    /**
     * Everything known about the subject in words, for the model to read.
     */
    public function analysisBrief(): string;

    /**
     * A website worth reading alongside the brief, if there is one.
     */
    public function analysisUrl(): ?string;
}
