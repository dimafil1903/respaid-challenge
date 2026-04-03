<?php

declare(strict_types=1);

namespace VoiceAgentQA;

use RuntimeException;

class ScenarioLoader
{
    public function load(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Scenario file not found: {$filePath}");
        }

        $json = file_get_contents($filePath);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in scenario file: ' . json_last_error_msg());
        }

        if (!isset($data['scenarios']) || !is_array($data['scenarios'])) {
            throw new RuntimeException("Scenario file must contain a 'scenarios' array");
        }

        $required = ['id', 'name', 'context', 'debtor_persona', 'expected_behaviors'];
        foreach ($data['scenarios'] as $i => $scenario) {
            foreach ($required as $field) {
                if (!isset($scenario[$field])) {
                    throw new RuntimeException("Scenario #{$i} missing required field: {$field}");
                }
            }
        }

        return $data['scenarios'];
    }

    public function findById(array $scenarios, string $id): ?array
    {
        foreach ($scenarios as $scenario) {
            if ($scenario['id'] === $id) {
                return $scenario;
            }
        }
        return null;
    }
}
