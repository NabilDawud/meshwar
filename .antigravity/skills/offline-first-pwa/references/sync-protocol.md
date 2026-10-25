# Low-Bandwidth Synchronization & Conflict Protocol

## 1. IndexedDB Outbox Structure
Store outbound mutations in an append-only transaction store:
```typescript
interface OutboxMutation {
  id: string;               // UUIDv4
  entity: 'booking' | 'offer' | 'request' | 'chat';
  action: 'create' | 'update' | 'cancel';
  payload: Record<string, unknown>;
  createdAt: number;        // Local timestamp
  attempts: number;
  lastAttemptAt?: number;
  status: 'pending' | 'syncing' | 'failed';
}
```

## 2. Sync Cycle Workflow
1. **Trigger**: Listen to `window.addEventListener('online', triggerSync)` and background periodic sync.
2. **Batching**: Compress pending outbox items into a single compact JSON array.
3. **Payload Budget Verification**:
   - Strip redundant metadata, identifiers, and nested models.
   - Enforce `< 10 KB` per outbound burst.
4. **Server Response**:
   - Returns applied mutation IDs and new server-side deltas (`{ applied: [...], deltas: [...], serverTime: ... }`).
   - Purge applied items from local outbox upon HTTP 200.

## 3. Media & Photo Quarantine
- Photos must be processed via a dedicated media queue separate from operational JSON sync.
- Check `navigator.connection?.saveData` or `navigator.connection?.type !== 'wifi'`.
- If on cellular/metered data:
  - Display lightweight svg/blur placeholder.
  - Show explicit tap-to-download badge: *"اضغط لتحميل صورة المركبة (120 ك.ب)"*.

## 4. Seat Race Conflicts
- Server logic checks availability at the instant of transaction processing.
- If seats are exhausted:
  - Server rejects mutation with `{ status: 'conflict', reason: 'seats_filled', remaining: 0 }`.
  - Client flags the local booking as rejected and surfaces a notification: *"عذراً، حجز شخص آخر قبلك أثناء انقطاع الاتصال"*.
