<?php

namespace WPEnhance\AI\Providers;

use WPEnhance\AI\Core\Config;
use WPEnhance\AI\Contracts\AIProviderInterface;

defined('ABSPATH') || exit;

/**
 * Creates an AI provider instance for a given WorkerConfig.
 *
 * The active provider (Anthropic / OpenAI) is still determined by the
 * WPENHANCE_AI_PROVIDER constant, but each feature can supply its own
 * WorkerConfig to control the exact model, token budget, and temperature
 * used for that specific task.
 *
 * Default models per provider (used when no config is passed):
 *   Anthropic → claude-haiku-4-5-20251001   (fast, cost-effective)
 *   OpenAI    → gpt-4o-mini
 *   Gemini    → gemini-2.0-flash
 */
class ProviderFactory {

    /** @var array<string, string> Default model per provider slug. */
    private const DEFAULT_MODELS = [
        'anthropic' => 'claude-haiku-4-5-20251001',
        'openai'    => 'gpt-4o-mini',
        'gemini'    => 'gemini-2.0-flash',
    ];

    public static function make(
        ?WorkerConfig $config = null
    ): AIProviderInterface {

        $provider = Config::provider();

        $config ??= new WorkerConfig(
            model: self::DEFAULT_MODELS[$provider]
                   ?? self::DEFAULT_MODELS['anthropic']
        );

        return match ($provider) {
            'anthropic' => new Anthropic($config),
            'openai'    => new OpenAI($config),
            'gemini'    => new Gemini($config),
            default     => new Anthropic($config),
        };
    }
}
