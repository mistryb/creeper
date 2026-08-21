<?php

namespace App\Enums;

use Laravel\Ai\Enums\Lab;

/**
 * A model provider a user can point Creeper at.
 *
 * Deliberately narrower than {@see Lab}: these are the providers that work
 * with nothing but an API key pasted into a form. Bedrock and Azure need more
 * than that, and Ollama needs no key at all, so neither belongs in a list of
 * "where should we send your key".
 */
enum CreepProvider: string
{
    case Anthropic = 'anthropic';
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case Groq = 'groq';
    case OpenRouter = 'openrouter';

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic',
            self::OpenAi => 'OpenAI',
            self::Gemini => 'Google Gemini',
            self::Groq => 'Groq',
            self::OpenRouter => 'OpenRouter',
        };
    }

    /**
     * What the key looks like, so the form can hint at the right one.
     */
    public function placeholder(): string
    {
        return match ($this) {
            self::Anthropic => 'sk-ant-...',
            self::OpenAi => 'sk-...',
            self::Gemini => 'AIza...',
            self::Groq => 'gsk_...',
            self::OpenRouter => 'sk-or-...',
        };
    }

    /**
     * The lab this provider maps onto in the AI SDK.
     */
    public function lab(): Lab
    {
        return match ($this) {
            self::Anthropic => Lab::Anthropic,
            self::OpenAi => Lab::OpenAI,
            self::Gemini => Lab::Gemini,
            self::Groq => Lab::Groq,
            self::OpenRouter => Lab::OpenRouter,
        };
    }
}
