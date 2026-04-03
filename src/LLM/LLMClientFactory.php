<?php

declare(strict_types=1);

namespace VoiceAgentQA\LLM;

use InvalidArgumentException;
use VoiceAgentQA\Config;

class LLMClientFactory
{
    public static function create(Config $config): LLMClientInterface
    {
        return match ($config->driver()) {
            'anthropic', 'mokksy' => new AnthropicClient($config),
            'openai' => new OpenAIClient($config),
            'mock' => new MockClient(),
            default => throw new InvalidArgumentException(
                "Unknown LLM driver: '{$config->driver()}'. Supported: anthropic, openai, mokksy, mock"
            ),
        };
    }
}
