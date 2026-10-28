# Superpowers: Systematic Debugging

## Purpose

When a test fails, a bug is reported, or an unexpected runtime error occurs, avoid guesswork or trial-and-error changes. Use a systematic protocol to isolate the root cause.

## The 4-Step Debugging Protocol

1. **Observe & Reproduce**:
   - Get the exact error message, stack trace, and HTTP status code.
   - Write a minimal failing test or replicate the exact request payload.
   - If a test cannot reproduce it, inspect environment variables and database state.

2. **Formulate a Hypothesis**:
   - Pinpoint the exact line and function where the expectation broke.
   - Formulate a falsifiable explanation (e.g., "The reverse proxy terminates SSL, so Laravel generates HTTP URLs instead of HTTPS").

3. **Verify Hypothesis**:
   - Check logs (`storage/logs/laravel.log`) or inspect runtime variables.
   - Prove the hypothesis before editing application logic.

4. **Apply Minimal Fix & Prove**:
   - Implement the smallest targeted correction.
   - Rerun the reproduction test.
   - Run the broader test suite to ensure zero regressions.
