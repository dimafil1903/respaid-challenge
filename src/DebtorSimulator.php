<?php

declare(strict_types=1);

namespace VoiceAgentQA;

class DebtorSimulator
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are simulating a debtor in a phone call with a payment resolution agent.

YOUR PERSONA:
{persona}

CONTEXT:
- Your name: {debtor_name}
- Company: {company}
- Invoice: {invoice_id} for ${invoice_amount}
- Days overdue: {days_overdue}

INSTRUCTIONS:
- Stay in character throughout the conversation
- Respond naturally as a real person would in this situation
- React to what the agent says — don't just repeat your initial position
- Keep responses concise (1-3 sentences, as in a real phone call)
PROMPT;

    private LLM\LLMClientInterface $llm;

    public function __construct(LLM\LLMClientInterface $llm)
    {
        $this->llm = $llm;
    }

    public function respond(array $scenario, array $conversationHistory): string
    {
        $systemPrompt = $this->buildSystemPrompt($scenario);
        return $this->llm->chat($systemPrompt, $conversationHistory);
    }

    private function buildSystemPrompt(array $scenario): string
    {
        $context = $scenario['context'];

        return str_replace(
            ['{persona}', '{debtor_name}', '{company}', '{invoice_id}', '{invoice_amount}', '{days_overdue}'],
            [
                $scenario['debtor_persona'],
                $context['debtor_name'],
                $context['company'],
                $context['invoice_id'],
                number_format($context['invoice_amount'], 2),
                (string) $context['days_overdue'],
            ],
            self::SYSTEM_PROMPT
        );
    }
}
