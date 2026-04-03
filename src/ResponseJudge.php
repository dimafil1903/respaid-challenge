<?php

declare(strict_types=1);

namespace VoiceAgentQA;

use RuntimeException;

class ResponseJudge
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert evaluator for AI voice agents that handle B2B invoice recovery calls.

Evaluate the agent's performance in the following conversation transcript. The agent works for Respaid, a company focused on recovering unpaid invoices while preserving business relationships.

SCENARIO: {scenario_name}
EXPECTED BEHAVIORS:
{expected_behaviors}

SCORING RUBRIC:

**Empathy (1-5):**
5: Acknowledges emotional state, validates feelings, offers specific help
4: Shows understanding, uses appropriate tone
3: Neutral, professional but no emotional acknowledgment
2: Dismissive or robotic
1: Aggressive, threatening, or completely ignoring emotions

**Accuracy (1-5):**
5: All expected behaviors followed, factually correct, no false promises
4: Most expected behaviors followed, minor omissions
3: Some expected behaviors missed, but no harmful errors
2: Significant expected behaviors missed or incorrect information given
1: Completely off-script, harmful or misleading statements

**Conversation Flow (1-5):**
5: Natural transitions, appropriate follow-ups, knows when to escalate
4: Generally smooth, minor awkwardness
3: Functional but robotic or repetitive
2: Disjointed, inappropriate topic changes
1: Incoherent or completely failing to advance the conversation

INSTRUCTIONS:
1. First, think step-by-step about the agent's performance (Chain of Thought)
2. Then provide your scores and reasoning

You MUST respond in this exact JSON format:
{
  "reasoning": "Your step-by-step analysis here...",
  "empathy": <1-5>,
  "accuracy": <1-5>,
  "flow": <1-5>,
  "notes": "Brief summary of key observations"
}

Respond ONLY with the JSON object, no other text.
PROMPT;

    private LLM\LLMClientInterface $llm;

    public function __construct(LLM\LLMClientInterface $llm)
    {
        $this->llm = $llm;
    }

    public function evaluate(array $scenario, array $transcript): array
    {
        $systemPrompt = $this->buildSystemPrompt($scenario);
        $transcriptText = $this->formatTranscript($transcript);

        $response = $this->llm->chat($systemPrompt, [
            ['role' => 'user', 'content' => "TRANSCRIPT:\n{$transcriptText}"],
        ], 2048);

        return $this->parseScores($response);
    }

    public function calculateWeightedScore(array $scores): float
    {
        return ($scores['empathy'] * 0.4) + ($scores['accuracy'] * 0.35) + ($scores['flow'] * 0.25);
    }

    private function buildSystemPrompt(array $scenario): string
    {
        $behaviors = implode("\n", array_map(
            fn(string $b) => "- {$b}",
            $scenario['expected_behaviors']
        ));

        return str_replace(
            ['{scenario_name}', '{expected_behaviors}'],
            [$scenario['name'], $behaviors],
            self::SYSTEM_PROMPT
        );
    }

    private function formatTranscript(array $transcript): string
    {
        $lines = [];
        foreach ($transcript as $message) {
            $role = $message['role'] === 'assistant' ? 'AGENT' : 'DEBTOR';
            $lines[] = "{$role}: {$message['content']}";
        }
        return implode("\n\n", $lines);
    }

    private function parseScores(string $response): array
    {
        $json = $response;
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $response, $matches)) {
            $json = $matches[1];
        }

        $data = json_decode(trim($json), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Judge returned invalid JSON: ' . json_last_error_msg() . "\nRaw response:\n{$response}");
        }

        $required = ['empathy', 'accuracy', 'flow'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || !is_numeric($data[$field])) {
                throw new RuntimeException("Judge response missing score: {$field}");
            }
        }

        return [
            'empathy' => (int) $data['empathy'],
            'accuracy' => (int) $data['accuracy'],
            'flow' => (int) $data['flow'],
            'reasoning' => $data['reasoning'] ?? '',
            'notes' => $data['notes'] ?? '',
        ];
    }
}
