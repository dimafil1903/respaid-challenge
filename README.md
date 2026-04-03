# Voice Agent QA Simulator

CLI tool that simulates test calls against AI voice agents and evaluates their performance automatically. Built for Respaid's B2B invoice recovery use case.

## Quick Start

```bash
composer install
cp .env.example .env   # add your API key
php bin/qa-simulator scenarios/default.json
```

## How It Works

The simulator uses a **3-role LLM architecture** — an industry-standard approach for voice agent testing:

```
Scenario JSON ──> Voice Agent (LLM #1)  <──┐
                        │                    │  multi-turn
                  Debtor Simulator (LLM #2) ─┘  conversation
                        │
                  Response Judge (LLM #3)
                        │
                  Score Report (pass/fail)
```

1. **Voice Agent** — the system under test, with a carefully designed system prompt that positions it as a "payment resolution specialist" (not a debt collector)
2. **Debtor Simulator** — plays the debtor role based on the scenario persona, maintaining character across turns
3. **Response Judge** — evaluates the full transcript using Chain-of-Thought reasoning and a detailed rubric

Each scenario runs a configurable number of back-and-forth turns (default: 3), then the judge scores the full conversation.

## Scoring

Three criteria, weighted by importance to invoice recovery:

| Criterion | Weight | Why |
|---|---|---|
| **Empathy** | 40% | Preserving business relationships is Respaid's core value |
| **Accuracy** | 35% | Compliance-critical: no false promises, follow expected behaviors |
| **Conversation Flow** | 25% | Natural transitions, knowing when to escalate |

Pass/fail threshold: **3.5/5.0** (configurable via `PASS_THRESHOLD`).

## CLI Usage

```bash
# Run all scenarios
php bin/qa-simulator scenarios/default.json

# Run a single scenario
php bin/qa-simulator scenarios/default.json -s angry-debtor

# Watch mode — re-run failed scenarios (leverages LLM non-determinism)
php bin/qa-simulator scenarios/default.json --watch --max-retries=3

# Show full conversation transcripts
php bin/qa-simulator scenarios/default.json --transcript
```

## LLM Drivers

Swap providers via `LLM_DRIVER` in `.env`:

| Driver | Description |
|---|---|
| `anthropic` | Claude API (default) |
| `openai` | OpenAI API (GPT-4o default) |
| `mokksy` | Anthropic-compatible proxy server |
| `mock` | Built-in canned responses for offline testing |

The Strategy pattern makes adding new providers trivial — implement `LLMClientInterface` and add a match arm in the factory.

## Scenarios

Six pre-built scenarios covering core voice agent challenges:

- **Already Paid** — debtor claims payment was made, agent must verify without accusing
- **Installment Plan** — debtor requests payment plan, agent must offer flexible options
- **Angry Debtor** — hostile caller, agent must de-escalate before discussing details
- **Dispute Invoice** — debtor disputes amount, agent must note details and escalate
- **Wrong Contact** — wrong person answers, agent must redirect politely
- **Cooperative Debtor** — ready to pay, agent must not overcomplicate

Create custom scenarios by adding entries to the JSON file — no code changes needed.

## Architecture Trade-offs

**Multi-turn vs single-turn:** Implemented 3-turn conversations as a balance between realism and speed. Single-turn wouldn't reflect real call dynamics; full 20-turn simulations would be slow and expensive.

**Same model for all roles:** All three LLM roles use the same model. In production, you'd use a cheaper model for the simulator and a stronger one for the judge.

**LLM-as-Judge scoring:** The judge uses CoT (Chain of Thought) reasoning before scoring, which research shows improves alignment with human evaluation. Rubrics with concrete level descriptors (not just "rate 1-5") further reduce scoring variance.

**Watch mode simplicity:** Re-runs the same prompt, relying on LLM non-determinism. Production would implement automated prompt refinement based on failure patterns.

**Console-only output:** No persistence — results go to stdout. Production would store results in a database for regression tracking.

## What I'd Add With More Time

- Parallel scenario execution (async HTTP calls)
- Per-call retry with exponential backoff
- HTML/JSON report export
- Scenario auto-generation from real call transcripts
- A/B testing of different system prompts
- Compliance checks (FDCPA/TCPA patterns)

## Tech Stack

- PHP 8.2+, Composer (PSR-4)
- Anthropic Claude SDK / OpenAI PHP SDK
- Symfony Console for CLI
- vlucas/phpdotenv for configuration
