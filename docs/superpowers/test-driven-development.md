# Superpowers: Test-Driven Development (TDD)

## The Iron Law

```
NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST.
```

If you didn't watch the test fail, you do not know whether the test actually proves the behavior or passes vacuously.

## The Red-Green-Refactor Cycle

1. **Red**:
   - Write a unit or feature test for the next smallest behavior.
   - Run the test (`php artisan test --filter=...`).
   - Confirm it fails for the expected reason (e.g., class missing, method missing, assertion failed).

2. **Green**:
   - Write the simplest, cleanest code that makes the test pass.
   - Do not write future code that isn't required by the current test.
   - Run the test and confirm it passes.

3. **Refactor**:
   - Clean up duplication and naming.
   - Run Pint (`vendor/bin/pint --dirty --format agent`).
   - Run the test again to verify no regressions occurred.

## Testing Best Practices in Mishwar

- Use Feature tests (`php artisan make:test SomeFeatureTest`) for HTTP endpoints and live state machines.
- Use Model factories (`User::factory()->create()`) instead of manual DB insertions.
- Keep tests isolated: each test creates its own data using transactions (`RefreshDatabase`).
- Test edge cases and authorization boundaries (e.g. driver cannot edit rider booking; rider cannot hijack driver route; unverified phone format rejected).
