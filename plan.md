# Voice Agent QA Simulator — Project Plan

## Respaid Context
Respaid (YC S23) — AI voice agents that contact businesses about unpaid invoices.  
Key insight: this is **not aggressive debt collection** — it's B2B invoice recovery focused on preserving business relationships.

---

## Architecture Decisions (this is what they evaluate!)

### 1. Three LLM Roles (industry standard from voicetest/voice-lab)
The industry standard approach is to separate concerns into three roles:
- **Agent** — Respaid's voice agent (system prompt designed by you)
- **Simulator** — plays the debtor role based on the scenario
- **Judge** — evaluates the transcript against criteria (LLM-as-Judge)

**Why this matters:** A naive single-turn "send scenario → get response → score" approach doesn't reflect how real voice agents work. Multi-turn simulation demonstrates understanding of the product domain. Within the 45-min constraint, a simplified version works well: 2-3 turns per scenario instead of a full conversation simulation.

### 2. LLM-as-Judge with CoT (Chain of Thought)
Not just "rate 1-5", but:
- Provide a **rubric** with specific criteria for each score level
- Request **reasoning** before the score (CoT)
- Require **structured JSON output**

Example rubric for empathy:
```
5: Acknowledges emotional state, validates feelings, offers specific help
4: Shows understanding, uses appropriate tone  
3: Neutral, professional but no emotional acknowledgment
2: Dismissive or robotic
1: Aggressive, threatening, or completely ignoring emotions
```

### 3. Scenario JSON — Flexible Structure
```json
{
  "scenarios": [
    {
      "id": "already-paid",
      "name": "Debtor claims already paid",
      "context": {
        "debtor_name": "John Smith",
        "company": "Smith & Co",
        "invoice_amount": 5200.00,
        "days_overdue": 45,
        "invoice_id": "INV-2024-0847"
      },
      "debtor_persona": "Frustrated business owner who insists payment was made last week via bank transfer. Gets increasingly annoyed if not believed.",
      "expected_behaviors": [
        "Acknowledge the claim politely",
        "Ask for payment reference or proof",
        "Offer to verify with accounts team",
        "Do NOT accuse of lying"
      ],
      "difficulty": "medium"
    }
  ]
}
```

**Trade-off:** Could have used a simple `message` field, but `context` + `debtor_persona` + `expected_behaviors` provides:
- Judge has concrete criteria for scoring
- Simulator knows how to play the role convincingly
- Easy to add new scenarios without code changes

### 4. Scoring Logic
**Pass/fail threshold:** overall score ≥ 3.5/5.0 across all three criteria

| Criterion | What's Evaluated | Weight |
|---|---|---|
| **Empathy** (1-5) | Tone, emotional acknowledgment, de-escalation | Critical for debt collection |
| **Accuracy** (1-5) | Factual correctness, following expected behaviors, no false promises | Compliance-critical |
| **Conversation Flow** (1-5) | Natural transitions, appropriate follow-ups, knowing when to escalate | UX quality |

**Weighted score:** `(empathy * 0.4) + (accuracy * 0.35) + (flow * 0.25)`  
Empathy has the highest weight — for invoice recovery, preserving relationships is critical.

---

## Project Structure

```
voice-agent-qa/
├── bin/
│   └── qa-simulator          # CLI entry point (PHP executable)
├── src/
│   ├── ScenarioLoader.php    # Reads & validates JSON scenarios
│   ├── VoiceAgent.php        # System prompt + LLM call (agent role)
│   ├── DebtorSimulator.php   # Simulates debtor responses (multi-turn)
│   ├── ResponseJudge.php     # LLM-as-Judge with CoT scoring
│   ├── LLMClient.php         # Anthropic API client (clean abstraction)
│   ├── ReportGenerator.php   # Console output + summary
│   └── Config.php            # API keys, thresholds, model selection
├── scenarios/
│   └── default.json          # 5-6 pre-built scenarios
├── composer.json
├── .env.example
└── README.md
```

### CLI Interface
```bash
# Run all scenarios
php bin/qa-simulator run scenarios/default.json

# Run specific scenario
php bin/qa-simulator run scenarios/default.json --scenario=already-paid

# Watch mode — re-run failed until pass or max retries
php bin/qa-simulator run scenarios/default.json --watch --max-retries=3

# Verbose — show full conversation transcript
php bin/qa-simulator run scenarios/default.json --verbose
```

---

## Scenarios (5-6, covering core use cases)

1. **"Already Paid"** — debtor claims payment was already made (check: agent doesn't accuse, asks for reference)
2. **"Installment Plan"** — debtor requests payment plan (check: agent offers options, doesn't refuse outright)
3. **"Angry Debtor"** — debtor is furious and aggressive (check: de-escalation, empathy, knows when to escalate)
4. **"Dispute Invoice"** — debtor disputes the amount/service (check: doesn't agree immediately, offers review process)
5. **"Wrong Contact"** — person says it's not their invoice (check: apologizes, asks for correct contact)
6. **"Cooperative Debtor"** — debtor is ready to pay immediately (check: doesn't overcomplicate, gives clear instructions)

---

## System Prompt for the Voice Agent (key artifact!)

This is the centerpiece — it shows how you think about Respaid's product:

```
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
```

---

## Watch Mode (--watch)

```
Algorithm:
1. Run all scenarios
2. Collect failed scenarios (score < threshold)
3. While failed_count > 0 AND retries < max_retries:
   a. Wait 2 seconds
   b. Re-run ONLY failed scenarios  
   c. Update results
4. Print final summary with [RETRY] markers
```

**Trade-off:** Watch mode re-runs with the same prompt, leveraging LLM non-determinism — it may pass on a second attempt. In production, this would implement automated prompt refinement based on failure patterns.

---

## Output Format

```
╔══════════════════════════════════════════════════════╗
║          Voice Agent QA Simulator - Report           ║
╠══════════════════════════════════════════════════════╣

Scenario: Already Paid (#already-paid)
  Empathy:           4/5  ✓
  Accuracy:          5/5  ✓
  Conversation Flow: 4/5  ✓
  Weighted Score:    4.25  → PASS
  Judge Notes: "Agent acknowledged payment claim promptly,
               asked for reference number, offered verification."

Scenario: Angry Debtor (#angry-debtor)
  Empathy:           2/5  ✗
  Accuracy:          4/5  ✓
  Conversation Flow: 3/5  ✓
  Weighted Score:    2.85  → FAIL
  Judge Notes: "Agent failed to de-escalate. Repeated invoice
               details when debtor was clearly frustrated."

╠══════════════════════════════════════════════════════╣
  Total: 5/6 passed (83%)
  Overall Score: 3.87/5.0
  Result: PASS (threshold: 3.5)
╚══════════════════════════════════════════════════════╝
```

---

## Tech Stack

- **PHP 8.2+** (they preferred)
- **Composer** for autoloading (PSR-4)
- **Anthropic Claude API** (claude-sonnet-4-20250514) — same model for agent/simulator/judge with different system prompts
- **No frameworks** — clean PHP, at most symfony/console for CLI (or even plain getopt)
- **dotenv** — for API keys
- **Guzzle or curl** — for HTTP requests

---

## README — What to Write (they WILL read this!)

### Approach
- Explain the 3-role architecture (agent/simulator/judge)
- Reference industry patterns (voicetest, LLM-as-Judge)
- Explain why empathy has the highest weight for invoice recovery

### Trade-offs (VERY IMPORTANT — they said "show us how you think")
1. **Multi-turn vs single-turn:** Implemented simplified multi-turn (2-3 exchanges) as a balance between realism and 45-min constraint. Production would use full conversation simulation with turn limits.
2. **Same model for all roles:** Using Claude Sonnet for agent/simulator/judge. In production, you'd want a cheaper model for simulation and a stronger model for judging.
3. **Static rubrics vs dynamic:** Rubrics are hardcoded. In production, these would be configurable per-scenario and tuned with human evaluation data.
4. **Watch mode simplicity:** Re-runs with same prompt (leveraging LLM non-determinism). Production would implement automated prompt refinement based on failure patterns.
5. **No persistence:** Results are console-only. Production would store in DB for regression tracking.

### What I'd Add With More Time
- Full multi-turn conversation simulation (10-20 turns)
- Compliance checks (FDCPA/TCPA patterns for US market)
- Parallel scenario execution
- HTML report generation
- Scenario auto-generation from real call transcripts
- A/B testing of different system prompts

---

## Timeline (45 minutes)

| Time | Task |
|---|---|
| 0-5 min | Scaffold: composer init, directories, .env |
| 5-12 min | LLMClient + Config (API wrapper) |
| 12-18 min | ScenarioLoader + scenarios JSON |
| 18-25 min | VoiceAgent (system prompt + call) |
| 25-30 min | ResponseJudge (scoring prompt + parsing) |
| 30-35 min | CLI entry point + ReportGenerator |
| 35-40 min | Watch mode |
| 40-45 min | README, testing, polish |

---

## Key Research Insights

### voicetest (voicetestdev/voicetest)
- Closest existing analogue. Three roles: simulator/agent/judge
- DSPy-based evaluation, DuckDB for persistence
- Supports Retell, VAPI, Bland, LiveKit
- **Borrowed idea:** test case structure with `user_prompt` + `metrics`

### voice-lab (saharmor/voice-lab)
- Simpler framework, focused on the text portion of voice agents
- JSON configuration with system_prompt + initial_message
- **Borrowed idea:** simplicity of scenario configuration

### LLM-as-Judge Best Practices
- **CoT prompting** — request reasoning before score (raises accuracy to ~85% human agreement)
- **Rubric-based scoring** — concrete criteria for each score level (1-5)
- **G-Eval framework** — generates evaluation steps, then scores
- **Structured output** — JSON with score + reasoning

### Debt Collection Voice Agents (Domain Knowledge)
- Empathy is #1 — preserving business relationships is critical
- Compliance (FDCPA, TCPA) — never threaten, always identify yourself
- Payment plan negotiation — core conversation flow
- Escalation triggers — knowing when to hand off to a human
- Right-party verification — confirming you're speaking with the correct person