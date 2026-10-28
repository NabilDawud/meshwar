# Superpowers: Writing Implementation Plans

## Purpose

Implementation plans translate high-level specifications and feature designs into concrete, bite-sized engineering steps. A well-written plan allows any engineer to execute the task without guesswork.

## Plan Location

Save all committed implementation plans to:
```
docs/superpowers/plans/YYYY-MM-DD-<feature-name>.md
```

## Plan Structure

1. **Context & Goals**:
   - Short summary of what is being built and why.
   - User constraints and relevant skills.

2. **File Structure & Touched Files**:
   - List every file that will be created, modified, or removed.
   - Keep responsibilities focused (single responsibility per class/view).

3. **Task Decomposition & Right-Sizing**:
   - Each task is a self-contained unit of deliverable value.
   - Tasks must be small enough to complete and test within 5–15 minutes.
   - Every task has an automated test or verification step.

4. **Step Granularity**:
   - Step 1: Write failing test (Red).
   - Step 2: Run test to confirm expected failure.
   - Step 3: Implement minimal production code (Green).
   - Step 4: Run test to confirm pass.
   - Step 5: Refactor and style check with `vendor/bin/pint`.
   - Step 6: Commit changes with descriptive conventional commit.
