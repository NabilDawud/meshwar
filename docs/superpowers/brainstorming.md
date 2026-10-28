# Superpowers: Brainstorming & Problem Exploration

## Purpose

Brainstorming is the first step before any creative engineering work — creating features, designing endpoints, building UI components, or planning major modifications. Its purpose is to discover what the user actually needs, understand the operational environment, and avoid building the wrong thing.

## Principles

1. **Look Before Asking**:
   - Inspect existing models, database migrations, controllers, and routes before asking the user basic questions.
   - Ground the discussion in how the application currently operates.

2. **Ruthless YAGNI (You Aren't Gonna Need It)**:
   - Challenge unnecessary complexity.
   - Do not design speculative abstractions or build features "just in case".
   - Keep solutions lightweight, performant, and maintainable.

3. **Operational Context (Gaza & Real-World Constraints)**:
   - Low bandwidth / offline-first: design for network interruptions (2G/EDGE or blackouts).
   - Simplicity: drivers are operating vehicles on roads; UIs must be high-contrast, large tap targets, and RTL-first.
   - Battery & Device Limits: avoid heavy background polling or battery-draining scripts.

4. **Sizing the Work**:
   - If the request is small and well-defined ("change this color to emerald"), execute it directly.
   - If the request introduces architecture, new flows, or security trade-offs, explore alternatives and present recommendations clearly before writing code.
