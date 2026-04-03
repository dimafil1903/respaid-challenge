<?php

declare(strict_types=1);

namespace VoiceAgentQA;

use Symfony\Component\Console\Output\OutputInterface;

class ReportGenerator
{
    private float $passThreshold;

    public function __construct(float $passThreshold)
    {
        $this->passThreshold = $passThreshold;
    }

    public function printResult(
        OutputInterface $output,
        array $scenario,
        array $scores,
        float $weightedScore,
        bool $isRetry = false,
    ): void {
        $pass = $weightedScore >= $this->passThreshold;
        $retryTag = $isRetry ? ' <comment>[RETRY]</comment>' : '';

        $output->writeln('');
        $output->writeln("Scenario: <info>{$scenario['name']}</info> (#{$scenario['id']}){$retryTag}");
        $output->writeln('  Empathy:           ' . $this->scoreDisplay($scores['empathy']));
        $output->writeln('  Accuracy:          ' . $this->scoreDisplay($scores['accuracy']));
        $output->writeln('  Conversation Flow: ' . $this->scoreDisplay($scores['flow']));
        $output->writeln(sprintf(
            '  Weighted Score:    %.2f  → %s',
            $weightedScore,
            $pass ? '<info>PASS</info>' : '<error>FAIL</error>'
        ));

        if (!empty($scores['notes'])) {
            $output->writeln("  Judge Notes: \"{$scores['notes']}\"");
        }
    }

    public function printSummary(OutputInterface $output, array $results): void
    {
        $total = count($results);
        $passed = count(array_filter($results, fn($r) => $r['weighted_score'] >= $this->passThreshold));
        $overallScore = $total > 0
            ? array_sum(array_column($results, 'weighted_score')) / $total
            : 0;

        $output->writeln('');
        $output->writeln('╠' . str_repeat('═', 54) . '╣');
        $output->writeln(sprintf(
            '  Total: %d/%d passed (%d%%)',
            $passed,
            $total,
            $total > 0 ? (int) round($passed / $total * 100) : 0
        ));
        $output->writeln(sprintf('  Overall Score: %.2f/5.0', $overallScore));
        $output->writeln(sprintf(
            '  Result: %s (threshold: %.1f)',
            $overallScore >= $this->passThreshold ? '<info>PASS</info>' : '<error>FAIL</error>',
            $this->passThreshold
        ));
        $output->writeln('╚' . str_repeat('═', 54) . '╝');
    }

    private function scoreDisplay(int $score): string
    {
        $check = $score >= 3 ? '<info>✓</info>' : '<error>✗</error>';
        return "{$score}/5  {$check}";
    }
}
