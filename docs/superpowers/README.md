# Superpowers Engineering Discipline

The **Superpowers** engineering framework governs development in **Mishwar (مشوار)** through disciplined, verified phases. It ensures every feature, bugfix, and refactoring step adheres to strict software craftsmanship, test-driven development, Ruthless YAGNI, and evidence-based verification.

---

## The Four Phases of Development

```
Phase 1: Clarification & Brainstorming
   ↓
Phase 2: Task Planning & Specification
   ↓
Phase 3: Test-Driven Execution (TDD)
   ↓
Phase 4: Systematic Verification & Review
```

### 1. Phase 1: Clarification & Brainstorming
- **Understand the Goal**: Identify user constraints, performance budgets, device limits, and non-negotiables.
- **Identify Unknowns**: Surface edge cases early (offline sync conflicts, iOS Safari quirks, mobile viewport issues, Palestinian carrier quirks).
- **Ruthless YAGNI**: Reject speculative features, premature optimizations, or unnecessary libraries. Build only what is needed today.
- *Detailed Guide*: [brainstorming.md](./brainstorming.md)

### 2. Phase 2: Design & Task Planning
- **Spec First**: Define API contracts, database schemas, and state transitions before code.
- **Bite-Sized Atomic Steps**: Break tasks into increments that can each be implemented, tested, and verified within minutes.
- **Verification Criteria**: Every step must have a concrete definition of done (e.g., passing automated test, visual verification in browser).
- **Plan Storage**: Implementation plans are saved to `docs/superpowers/plans/YYYY-MM-DD-<feature-name>.md`.
- *Detailed Guide*: [writing-plans.md](./writing-plans.md)

### 3. Phase 3: Test-Driven Execution (TDD)
- **The Iron Law**: *No production code without a failing test first.*
- **Red-Green-Refactor**:
  1. Write a failing unit or feature test defining expected behavior.
  2. Implement minimal code to pass the test.
  3. Refactor for clarity, performance, and adherence to project rules.
- **Never Fake Tests**: Ensure assertions test actual functionality, not mocked illusions.
- *Detailed Guide*: [test-driven-development.md](./test-driven-development.md) & [executing-plans.md](./executing-plans.md)

### 4. Phase 4: Systematic Verification
- **Evidence Before Claims**: Never claim a feature is complete or a bug is fixed without fresh execution output.
- **Automated Tests**: Run test suites (`php artisan test --compact`, `vendor/bin/pint`).
- **Real Browser Verification**: Load in mobile viewport (e.g., iPhone SE 375px, Pixel 7), inspect console errors, audit accessibility, and confirm responsive layouts.
- *Detailed Guide*: [verification.md](./verification.md) & [systematic-debugging.md](./systematic-debugging.md)

---

## Directory Structure

```
docs/
├── PLAN.md                     # High-level architecture and milestone plan
└── superpowers/
    ├── README.md               # Framework overview (this file)
    ├── brainstorming.md        # Clarification & problem space exploration
    ├── writing-plans.md        # Plan creation, task sizing, and step breakdown
    ├── executing-plans.md      # Execution discipline and batching
    ├── test-driven-development.md # TDD red-green-refactor rules
    ├── systematic-debugging.md # Root cause investigation protocol
    ├── verification.md         # Gate function & evidence requirements
    └── plans/                  # Committed implementation plans for features
```
