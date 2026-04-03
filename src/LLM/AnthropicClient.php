<?php

declare(strict_types=1);

namespace VoiceAgentQA\LLM;

use Anthropic\Client;
use VoiceAgentQA\Config;

class AnthropicClient implements LLMClientInterface
{
    private Client $client;
    private string $model;

    public function __construct(Config $config)
    {
        $this->client = new Client(
            apiKey: $config->apiKey(),
            baseUrl: $config->baseUrl(),
        );
        $this->model = $config->model();
    }

    public function chat(string $systemPrompt, array $messages, int $maxTokens = 1024): string
    {
        $response = $this->client->messages->create(
            model: $this->model,
            maxTokens: $maxTokens,
            system: $systemPrompt,
            messages: $messages,
        );

        return $response->content[0]->text;
    }
}
