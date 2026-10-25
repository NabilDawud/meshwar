---
name: on-air
description: Use during live-streamed, recorded, or presentation sessions where the agent speaks status updates aloud using Windows System.Speech and coordinates manual tasks with Nabil.
---

# On-Air Presentation & Voice Protocol

Used during recorded or live presentation sessions with an audience.

## Voice Command
Execute speech via PowerShell in the background:
```powershell
Add-Type -AssemblyName System.Speech; (New-Object System.Speech.Synthesis.SpeechSynthesizer).Speak("<text>")
```

## Speech Triggers (WHEN)
Speak aloud:
1. **Identity**: When asked who you are.
2. **Start of Work**: When starting a piece of work (state what and why in one line).
3. **Finish of Prompt**: When finishing a prompt (state what now works in one line).
4. **Milestone Delivery**: When a feature or milestone is ready for review.
5. **Failures**: When a command, test, or build fails.

## Speech Standards (HOW)
- **Language**: English only.
- **Length**: One or two short sentences, strictly under 25 words.
- **Style**: Plain spoken conversational words. Never speak code, file paths, commands, hashes, tokens, or long numbers aloud.
- **Transcript Mirror**: Always print the exact spoken sentence on screen preceded by `> `.

## Limits & Resilience
- Never speak more than once a minute while working.
- Speak only at the start and end of a step, never mid-stream.
- Never repeat yourself.
- If speech synthesis fails or audio is muted, print with `> ` and proceed immediately. The voice never blocks the work.

## Coordination: "NEEDED FROM NABIL"
Whenever Nabil must perform an action outside this terminal (e.g., in Chrome, on GitHub, or on a mobile device):
1. Stop tool execution.
2. Render a clear numbered box:
   ```markdown
   ### NEEDED FROM NABIL
   1. [Exact page / URL]
   2. [Exact button or menu]
   3. [What to enter / confirm]
   ```
3. Speak out loud in one sentence what Nabil needs to do.
4. Wait until Nabil responds "done".
