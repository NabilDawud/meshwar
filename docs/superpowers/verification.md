# Superpowers: Verification Before Completion

## The Gate Function

```
NO COMPLETION CLAIMS WITHOUT FRESH VERIFICATION EVIDENCE.
```

Never assume code works because "it should" or "it worked earlier". Evidence must precede every status report.

## Verification Checklist

1. **Automated Test Suite**:
   ```bash
   php artisan test --compact
   ```
   Must pass with 0 failures and 0 errors.

2. **Code Style & Static Analysis**:
   ```bash
   vendor/bin/pint --dirty --format agent
   ```
   Must produce 0 styling issues.

3. **Frontend & Mobile Viewport**:
   - Inspect layout on mobile screen widths (360px, 375px, 412px).
   - Ensure no horizontal scrollbars (`overflow-x: hidden`).
   - Check touch targets (minimum 44×44px).
   - Check RTL orientation and Arabic text alignment.

4. **Git Status Cleanliness**:
   ```bash
   git status
   ```
   Ensure no stray untracked debugging files, temporary scripts, or dirty working trees remain.
