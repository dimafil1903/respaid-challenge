<?php

declare(strict_types=1);

namespace VoiceAgentQA\LLM;

interface LLMClientInterface
{
    public function chat(string $systemPrompt, array $messages, int $maxTokens = 1024): string;
}
