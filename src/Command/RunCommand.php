<?php

declare(strict_types=1);

namespace VoiceAgentQA\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VoiceAgentQA\Config;
use VoiceAgentQA\DebtorSimulator;
use VoiceAgentQA\LLM\LLMClientFactory;
use VoiceAgentQA\LLM\LoggingClient;
use VoiceAgentQA\ReportGenerator;
use VoiceAgentQA\ResponseJudge;
use VoiceAgentQA\ScenarioLoader;
use VoiceAgentQA\VoiceAgent;

class RunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDescription('Run QA scenarios against the voice agent')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to scenarios JSON file')
            ->addOption('scenario', 's', InputOption::VALUE_REQUIRED, 'Run a specific scenario by ID')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Re-run failed scenarios until pass or max retries')
            ->addOption('max-retries', 'r', InputOption::VALUE_REQUIRED, 'Max retries in watch mode', '3')
            ->addOption('transcript', 't', InputOption::VALUE_NONE, 'Show full conversation transcripts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new Config();
        $rawLlm = LLMClientFactory::create($config);
        $llm = new LoggingClient($rawLlm, $output);
        $loader = new ScenarioLoader();
        $agent = new VoiceAgent($llm);
        $simulator = new DebtorSimulator($llm);
        $judge = new ResponseJudge($llm);
        $report = new ReportGenerator($config->passThreshold());

        $output->writeln("<info>Driver:</info> {$config->driver()}  <info>Model:</info> {$config->model()}");

        $scenarios = $loader->load($input->getArgument('file'));

        $scenarioId = $input->getOption('scenario');
        if ($scenarioId) {
            $found = $loader->findById($scenarios, $scenarioId);
            if (!$found) {
                $output->writeln("<error>Scenario not found: {$scenarioId}</error>");
                return Command::FAILURE;
            }
            $scenarios = [$found];
        }

        $output->writeln('╔' . str_repeat('═', 54) . '╗');
        $output->writeln('║' . str_pad('Voice Agent QA Simulator - Report', 54, ' ', STR_PAD_BOTH) . '║');
        $output->writeln('╠' . str_repeat('═', 54) . '╣');

        $showTranscript = $input->getOption('transcript');
        $results = $this->runScenarios($scenarios, $agent, $simulator, $judge, $report, $config, $output, $showTranscript);

        // Watch mode: re-run failed scenarios
        if ($input->getOption('watch')) {
            $maxRetries = (int) $input->getOption('max-retries');

            for ($retry = 1; $retry <= $maxRetries; $retry++) {
                $failed = array_filter($results, fn($r) => $r['weighted_score'] < $config->passThreshold());
                if (empty($failed)) {
                    break;
                }

                $failedCount = count($failed);
                $output->writeln("\n<comment>--- Retry {$retry}/{$maxRetries} ({$failedCount} failed) ---</comment>");
                sleep(2);

                $failedScenarios = array_map(fn($r) => $r['scenario'], $failed);
                $retryResults = $this->runScenarios(
                    array_values($failedScenarios), $agent, $simulator, $judge, $report, $config, $output, $showTranscript, true
                );

                foreach ($retryResults as $retryResult) {
                    foreach ($results as $key => $result) {
                        if ($result['scenario']['id'] === $retryResult['scenario']['id']) {
                            $results[$key] = $retryResult;
                            break;
                        }
                    }
                }
            }
        }

        $report->printSummary($output, $results);

        $overallScore = count($results) > 0
            ? array_sum(array_column($results, 'weighted_score')) / count($results)
            : 0;

        return $overallScore >= $config->passThreshold() ? Command::SUCCESS : Command::FAILURE;
    }

    private function runScenarios(
        array $scenarios,
        VoiceAgent $agent,
        DebtorSimulator $simulator,
        ResponseJudge $judge,
        ReportGenerator $report,
        Config $config,
        OutputInterface $output,
        bool $showTranscript,
        bool $isRetry = false,
    ): array {
        $results = [];

        foreach ($scenarios as $scenario) {
            $output->writeln("\n<comment>Running: {$scenario['name']}...</comment>");

            try {
                // Build multi-turn conversation
                $transcript = [];

                // Agent opens the call
                $output->write('  <info>[agent]</info> Opening call... ');
                $agentOpener = $agent->respond($scenario, [
                    ['role' => 'user', 'content' => 'The call has just connected. Begin the conversation.'],
                ]);
                $transcript[] = ['role' => 'assistant', 'content' => $agentOpener];
                $output->writeln('OK');

                // Multi-turn exchange
                for ($turn = 0; $turn < $config->maxTurns(); $turn++) {
                    $turnNum = $turn + 1;

                    // Debtor responds (inverted roles: agent messages → user, debtor → assistant)
                    $output->write("  <info>[simulator]</info> Turn {$turnNum}/{$config->maxTurns()}... ");
                    $debtorMessages = $this->invertRoles($transcript);
                    $debtorResponse = $simulator->respond($scenario, $debtorMessages);
                    $transcript[] = ['role' => 'user', 'content' => $debtorResponse];
                    $output->writeln('OK');

                    // Agent responds
                    $output->write("  <info>[agent]</info> Turn {$turnNum}/{$config->maxTurns()}... ");
                    $agentResponse = $agent->respond($scenario, $transcript);
                    $transcript[] = ['role' => 'assistant', 'content' => $agentResponse];
                    $output->writeln('OK');
                }

                if ($showTranscript) {
                    $output->writeln('  <info>--- Transcript ---</info>');
                    foreach ($transcript as $msg) {
                        $role = $msg['role'] === 'assistant' ? 'AGENT' : 'DEBTOR';
                        $output->writeln("  {$role}: {$msg['content']}");
                    }
                    $output->writeln('  <info>--- End Transcript ---</info>');
                }

                // Judge evaluates the full transcript
                $output->write('  <info>[judge]</info> Evaluating... ');
                $scores = $judge->evaluate($scenario, $transcript);
                $weightedScore = $judge->calculateWeightedScore($scores);
                $output->writeln('OK');

                $report->printResult($output, $scenario, $scores, $weightedScore, $isRetry);

                $results[] = [
                    'scenario' => $scenario,
                    'scores' => $scores,
                    'weighted_score' => $weightedScore,
                    'transcript' => $transcript,
                ];
            } catch (\Throwable $e) {
                $output->writeln('<error>FAILED</error>');
                $output->writeln("  <error>Error: {$e->getMessage()}</error>");

                $results[] = [
                    'scenario' => $scenario,
                    'scores' => ['empathy' => 0, 'accuracy' => 0, 'flow' => 0, 'reasoning' => '', 'notes' => ''],
                    'weighted_score' => 0.0,
                    'transcript' => [],
                ];
            }
        }

        return $results;
    }

    /**
     * Invert roles so the simulator sees the conversation from the debtor's perspective.
     */
    private function invertRoles(array $transcript): array
    {
        return array_map(fn($msg) => [
            'role' => $msg['role'] === 'assistant' ? 'user' : 'assistant',
            'content' => $msg['content'],
        ], $transcript);
    }
}
