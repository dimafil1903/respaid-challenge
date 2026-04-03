<?php

declare(strict_types=1);

namespace VoiceAgentQA;

use Dotenv\Dotenv;
use RuntimeException;

class Config
{
    private string $driver;
    private string $apiKey;
    private string $model;
    private ?string $baseUrl;
    private string $openaiApiKey;
    private string $openaiModel;
    private float $passThreshold;
    private int $maxTurns;

    public function __construct(?string $envPath = null)
    {
        $envPath = $envPath ?? dirname(__DIR__);

        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->safeLoad();

        $this->driver = $this->env('LLM_DRIVER', 'anthropic');
        $this->baseUrl = $this->env('ANTHROPIC_BASE_URL');

        $this->apiKey = $this->env('ANTHROPIC_API_KEY', '');
        if (in_array($this->driver, ['anthropic', 'mokksy']) && empty($this->apiKey)) {
            throw new RuntimeException(
                'ANTHROPIC_API_KEY is required. Copy .env.example to .env and set your key.'
            );
        }

        if ($this->driver === 'mokksy' && empty($this->baseUrl)) {
            throw new RuntimeException(
                'ANTHROPIC_BASE_URL is required when using the mokksy driver. '
                . 'Set it to your Mokksy server URL (e.g., http://localhost:8080).'
            );
        }

        $this->openaiApiKey = $this->env('OPENAI_API_KEY', '');
        if ($this->driver === 'openai' && empty($this->openaiApiKey)) {
            throw new RuntimeException(
                'OPENAI_API_KEY is required when using the openai driver.'
            );
        }

        $this->model = $this->env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514');
        $this->openaiModel = $this->env('OPENAI_MODEL', 'gpt-4o');
        $this->passThreshold = (float) $this->env('PASS_THRESHOLD', '3.5');
        $this->maxTurns = (int) $this->env('MAX_TURNS', '3');
    }

    private function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key) ?: null;
        return $value ?? $default;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function passThreshold(): float
    {
        return $this->passThreshold;
    }

    public function maxTurns(): int
    {
        return $this->maxTurns;
    }

    public function openaiApiKey(): string
    {
        return $this->openaiApiKey;
    }

    public function openaiModel(): string
    {
        return $this->openaiModel;
    }
}
