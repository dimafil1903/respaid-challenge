<?php

declare(strict_types=1);

namespace VoiceAgentQA\LLM;

use Symfony\Component\Console\Output\OutputInterface;

class LoggingClient implements LLMClientInterface
{
    private LLMClientInterface $inner;
    private OutputInterface $output;
    private int $callCount = 0;

    public function __construct(LLMClientInterface $inner, OutputInterface $output)
    {
        $this->inner = $inner;
        $this->output = $output;
    }

    public function chat(string $systemPrompt, array $messages, int $maxTokens = 1024): string
    {
        $this->callCount++;
        $role = $this->detectRole($systemPrompt);
        $msgCount = count($messages);
        $promptLen = strlen($systemPrompt);

        $this->output->writeln(
            "    <fg=gray>#{$this->callCount} [{$role}] "
            . "messages={$msgCount} system_prompt={$promptLen}chars max_tokens={$maxTokens}</>"
        );

        $startTime = microtime(true);

        try {
            $response = $this->inner->chat($systemPrompt, $messages, $maxTokens);
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $startTime) * 1000);
            $this->output->writeln(
                "    <error>#{$this->callCount} FAILED after {$elapsed}ms: {$e->getMessage()}</error>"
            );
            throw $e;
        }

        $elapsed = round((microtime(true) - $startTime) * 1000);
        $responseLen = strlen($response);
        $this->output->writeln(
            "    <fg=gray>#{$this->callCount} OK {$elapsed}ms response={$responseLen}chars</>"
        );

        return $response;
    }

    private function detectRole(string $systemPrompt): string
    {
        if (str_contains($systemPrompt, 'voice agent for Respaid')) {
            return 'agent';
        }
        if (str_contains($systemPrompt, 'simulating a debtor')) {
            return 'simulator';
        }
        if (str_contains($systemPrompt, 'expert evaluator')) {
            return 'judge';
        }
        return 'unknown';
    }
}
