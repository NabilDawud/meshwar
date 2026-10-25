---
name: superpowers
description: Enforces disciplined engineering workflows (Brainstorm -> Plan -> TDD/Execute -> Verify). Activate whenever planning, designing, or implementing new features to ensure strict adherence to YAGNI, small atomic steps, and verified testing gates.
---

# Superpowers Engineering Discipline

This skill governs end-to-end development through disciplined, verified phases. Never skip phases or write speculative code.

## 1. Phase 1: Clarification & Brainstorming
- **Understand the Goal**: Identify user constraints, performance budgets, device limits, and non-negotiables.
- **Identify Unknowns**: Surface edge cases early (e.g., offline sync conflicts, iOS Safari quirks, token expirations).
- **Ruthless YAGNI**: Reject unnecessary libraries, premature optimizations, or speculative features. Build only what is needed today.

## 2. Phase 2: Design & Task Planning
- **Spec First**: Define API contracts, database schemas, and state transitions before code.
- **Bite-Sized Atomic Steps**: Break tasks into increments that can each be implemented, tested, and verified within minutes.
- **Verification Criteria**: Every step must have a concrete definition of done (e.g., passing automated test, visual verification in browser).

## 3. Phase 3: Test-Driven Execution (TDD)
- **Red-Green-Refactor**:
  1. Write a failing unit or integration test defining expected behavior.
  2. Implement minimal code to pass the test.
  3. Refactor for clarity, performance, and adherence to project rules.
- **Never Fake Tests**: Ensure assertions test actual functionality, not mocked illusions.

## 4. Phase 4: Systematic Verification
- **Automated Tests**: Run test suites (`php artisan test` / `pest`, `npm test`).
- **Real Browser Verification**: Use `chrome-devtools` MCP to load the page in a mobile viewport, inspect console errors, audit accessibility, and confirm visual layout.
- **Gate Check**: Never mark a task complete without passing all verification gates.
