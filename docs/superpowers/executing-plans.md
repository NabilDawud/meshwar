# Superpowers: Executing Implementation Plans

## Purpose

Executing plans requires discipline, focus, and adherence to the step-by-step roadmap. Do not jump ahead, do not skip tests, and do not introduce unrequested features mid-execution.

## Execution Rules

1. **Follow the Order**:
   - Execute tasks in the documented sequence.
   - If an unexpected blocker arises, pause, assess, and update the plan rather than improvising.

2. **Commit Early & Often**:
   - Commit at the conclusion of each passing task.
   - Use atomic, descriptive commit messages (e.g., `feat(profile): add name and phone update endpoint`).

3. **Keep Context Clean**:
   - Focus on one module at a time.
   - Run focused tests (`php artisan test --filter=ProfileTest`) during development, and the full suite before completion.

4. **Feedback Loop**:
   - Always verify the behavior in the actual environment (browser, API client, or test suite) before declaring a task finished.
