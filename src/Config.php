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
    private float $passThreshold;
    private int $maxTurns;

    public function __construct(?string $envPath = null)
    {
        $envPath = $envPath ?? dirname(__DIR__);

        $dotenv = Dotenv::createImmutable($envPath);
        $dotenv->safeLoad();

        $this->driver = $_ENV['LLM_DRIVER'] ?? getenv('LLM_DRIVER') ?: 'anthropic';
        $this->baseUrl = $_ENV['ANTHROPIC_BASE_URL'] ?? getenv('ANTHROPIC_BASE_URL') ?: null;

        $this->apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? getenv('ANTHROPIC_API_KEY') ?: '';
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

        $this->model = $_ENV['ANTHROPIC_MODEL'] ?? getenv('ANTHROPIC_MODEL') ?: 'claude-sonnet-4-20250514';
        $this->passThreshold = (float) ($_ENV['PASS_THRESHOLD'] ?? getenv('PASS_THRESHOLD') ?: 3.5);
        $this->maxTurns = (int) ($_ENV['MAX_TURNS'] ?? getenv('MAX_TURNS') ?: 3);
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
}
