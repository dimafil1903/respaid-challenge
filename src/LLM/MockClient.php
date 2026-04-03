<?php

declare(strict_types=1);

namespace VoiceAgentQA\LLM;

class MockClient implements LLMClientInterface
{
    private int $callCount = 0;

    public function chat(string $systemPrompt, array $messages, int $maxTokens = 1024): string
    {
        $this->callCount++;
        $role = $this->detectRole($systemPrompt);

        return match ($role) {
            'agent' => $this->agentResponse($systemPrompt, $messages),
            'simulator' => $this->simulatorResponse($systemPrompt, $messages),
            'judge' => $this->judgeResponse($systemPrompt, $messages),
            default => '[MockClient] Unknown role — system prompt did not match any known pattern.',
        };
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }

    public function reset(): void
    {
        $this->callCount = 0;
    }

    private function detectRole(string $systemPrompt): string
    {
        if (str_contains($systemPrompt, 'payment resolution specialist') || str_contains($systemPrompt, 'voice agent for Respaid')) {
            return 'agent';
        }
        if (str_contains($systemPrompt, 'simulating a debtor') || str_contains($systemPrompt, 'YOUR PERSONA')) {
            return 'simulator';
        }
        if (str_contains($systemPrompt, 'expert evaluator') || str_contains($systemPrompt, 'SCORING RUBRIC')) {
            return 'judge';
        }
        return 'unknown';
    }

    private function agentResponse(string $systemPrompt, array $messages): string
    {
        $turn = count($messages);

        if ($turn <= 1) {
            return 'Hello, this is Sarah from Respaid. I\'m calling regarding an outstanding invoice '
                . 'on your account. I\'d like to help find a resolution that works for both of us. '
                . 'Do you have a moment to discuss this?';
        }

        $lastMessage = strtolower(end($messages)['content'] ?? '');

        if (str_contains($lastMessage, 'already paid') || str_contains($lastMessage, 'paid')) {
            return 'I completely understand, and I appreciate you letting me know. '
                . 'Could you provide me with the payment reference number or the date of the transfer? '
                . 'I\'d be happy to verify this with our accounts team right away.';
        }

        if (str_contains($lastMessage, 'angry') || str_contains($lastMessage, 'frustrated') || str_contains($lastMessage, 'furious')) {
            return 'I hear you, and I\'m sorry for the frustration this is causing. '
                . 'I want to make sure we resolve this properly. Would it be better if I called back '
                . 'at a more convenient time, or would you like to discuss options now?';
        }

        if (str_contains($lastMessage, 'payment plan') || str_contains($lastMessage, 'installment') || str_contains($lastMessage, 'can\'t pay')) {
            return 'Absolutely, we can work something out. We offer flexible payment plans — '
                . 'would a monthly installment arrangement work for you? '
                . 'I can outline a few options based on your situation.';
        }

        if (str_contains($lastMessage, 'dispute') || str_contains($lastMessage, 'wrong amount') || str_contains($lastMessage, 'not correct')) {
            return 'I understand your concern about the invoice amount. I\'ll make a note of this dispute '
                . 'and connect you with our accounts team who can review the details. '
                . 'Would you be available for a follow-up call once they\'ve looked into it?';
        }

        if (str_contains($lastMessage, 'wrong person') || str_contains($lastMessage, 'not me') || str_contains($lastMessage, 'wrong number')) {
            return 'I sincerely apologize for the inconvenience. Could you possibly direct me to the right person '
                . 'who handles accounts payable at your company? Thank you for your patience.';
        }

        return 'Thank you for that information. I want to make sure we find the best solution for you. '
            . 'Is there anything specific about this invoice you\'d like to discuss, '
            . 'or shall I go over the available options?';
    }

    private function simulatorResponse(string $systemPrompt, array $messages): string
    {
        $persona = $this->extractPersonaHint($systemPrompt);
        $turn = count($messages);

        return match (true) {
            str_contains($persona, 'already paid') || str_contains($persona, 'insists payment') => match (true) {
                $turn <= 2 => 'I already paid this last week via bank transfer. Check your records.',
                $turn <= 4 => 'I have the reference number somewhere — let me look... it was a wire transfer on Monday.',
                default => 'Fine, the reference is TXN-20240315. Can you verify that please?',
            },
            str_contains($persona, 'furious') || str_contains($persona, 'aggressive') || str_contains($persona, 'angry') => match (true) {
                $turn <= 2 => 'Are you kidding me? I\'ve been dealing with this for weeks! This is ridiculous!',
                $turn <= 4 => 'Look, I\'m just tired of being called about this. What exactly do you need from me?',
                default => 'Fine. Just tell me what I need to do to resolve this once and for all.',
            },
            str_contains($persona, 'installment') || str_contains($persona, 'payment plan') || str_contains($persona, 'financial difficulty') => match (true) {
                $turn <= 2 => 'I know about the invoice, but we\'re going through a rough patch financially. I can\'t pay the full amount right now.',
                $turn <= 4 => 'A payment plan could work. What kind of monthly amounts are we talking about?',
                default => 'Three months sounds reasonable. Let\'s do that.',
            },
            str_contains($persona, 'disputes') || str_contains($persona, 'dispute') => match (true) {
                $turn <= 2 => 'Wait, that amount doesn\'t match what we agreed on. The service wasn\'t fully delivered.',
                $turn <= 4 => 'We only received about 80% of what was promised. I\'m not paying for work that wasn\'t done.',
                default => 'Okay, having your accounts team review it sounds fair. When can I expect to hear back?',
            },
            str_contains($persona, 'wrong') || str_contains($persona, 'not their') => match (true) {
                $turn <= 2 => 'I think you have the wrong person. I don\'t handle invoices here.',
                default => 'You\'d want to speak with our accounts department. Try asking for Janet.',
            },
            str_contains($persona, 'cooperative') || str_contains($persona, 'ready to pay') => match (true) {
                $turn <= 2 => 'Yes, I\'m aware of the invoice. I\'ve been meaning to take care of it. What\'s the easiest way to pay?',
                default => 'Great, I\'ll process the payment today. Can you send me the details by email?',
            },
            default => 'I understand. Can you give me more details about this?',
        };
    }

    private function judgeResponse(string $systemPrompt, array $messages): string
    {
        return json_encode([
            'reasoning' => '[Mock] The agent demonstrated professional behavior, '
                . 'acknowledged the debtor\'s situation, and offered appropriate solutions. '
                . 'The conversation flowed naturally with proper follow-ups.',
            'empathy' => 4,
            'accuracy' => 4,
            'flow' => 4,
            'notes' => 'Mock evaluation — agent showed good empathy and followed expected behaviors.',
        ], JSON_PRETTY_PRINT);
    }

    private function extractPersonaHint(string $systemPrompt): string
    {
        if (preg_match('/YOUR PERSONA:\s*(.+?)(?:\n\n|CONTEXT:)/s', $systemPrompt, $matches)) {
            return strtolower(trim($matches[1]));
        }
        return strtolower($systemPrompt);
    }
}
