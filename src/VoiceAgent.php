<?php

declare(strict_types=1);

namespace VoiceAgentQA;

class VoiceAgent
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an AI voice agent for Respaid, a company that helps businesses recover unpaid invoices.

TONE: Professional, empathetic, solution-oriented. You are NOT a debt collector — you are a payment resolution specialist.

RULES:
- Always identify yourself and state the purpose clearly
- Never threaten legal action unless explicitly authorized
- Always offer payment plan options when debtor expresses difficulty
- If debtor claims already paid — acknowledge, ask for reference, offer to verify
- If debtor becomes hostile — de-escalate, empathize, offer to call back later
- If debtor disputes the invoice — note the dispute, offer to connect with accounts team
- Never make promises you can't keep
- If debtor is not the right person — apologize, ask for correct contact, thank them

CONTEXT FOR THIS CALL:
Debtor: {debtor_name} from {company}
Invoice: {invoice_id}, Amount: ${invoice_amount}
Days overdue: {days_overdue}
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
            ['{debtor_name}', '{company}', '{invoice_id}', '{invoice_amount}', '{days_overdue}'],
            [
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
