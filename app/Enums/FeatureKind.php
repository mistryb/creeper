<?php

namespace App\Enums;

/**
 * What sort of change a changelog entry is.
 *
 * The one list: the changelog agent is asked for these values, the payload
 * validates against them, and the UI colours them by it.
 */
enum FeatureKind: string
{
    case Feature = 'feature';
    case Improvement = 'improvement';
    case Fix = 'fix';
    case Breaking = 'breaking';
    case Deprecation = 'deprecation';
    case Security = 'security';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'New',
            self::Improvement => 'Improved',
            self::Fix => 'Fixed',
            self::Breaking => 'Breaking',
            self::Deprecation => 'Deprecated',
            self::Security => 'Security',
            self::Other => 'Other',
        };
    }

    /**
     * The kinds an agent may report, for a schema or a validation rule.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Map the many ways an agent might label a change onto the enum.
     */
    public static function parse(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return self::Other;
        }

        $normalized = str_replace([' ', '-'], '_', mb_strtolower(trim($value)));

        return match ($normalized) {
            'feature', 'new', 'added', 'addition', 'features' => self::Feature,
            'improvement', 'improved', 'changed', 'change', 'enhancement', 'performance' => self::Improvement,
            'fix', 'fixed', 'bugfix', 'bug_fix', 'bug', 'patch' => self::Fix,
            'breaking', 'breaking_change', 'removed', 'removal' => self::Breaking,
            'deprecation', 'deprecated' => self::Deprecation,
            'security' => self::Security,
            default => self::Other,
        };
    }
}
