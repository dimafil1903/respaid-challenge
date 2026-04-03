<?php

declare(strict_types=1);

namespace VoiceAgentQA\LLM;

use OpenAI;
use VoiceAgentQA\Config;

class OpenAIClient implements LLMClientInterface
{
    private OpenAI\Client $client;
    private string $model;

    public function __construct(Config $config)
    {
        $this->client = OpenAI::client($config->openaiApiKey());
        $this->model = $config->openaiModel();
    }

    public function chat(string $systemPrompt, array $messages, int $maxTokens = 1024): string
    {
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($messages as $message) {
            $apiMessages[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'messages' => $apiMessages,
        ]);

        return $response->choices[0]->message->content;
    }
}
