# TASK (local GLM-5.2, Kilo on PC) — Parent AI Chat screen   [DO NOT DELETE THIS FILE]

**START:** `git pull --no-edit`. Work only in `edifis-mobile/`.
**END:** `flutter analyze` clean → `git commit && git push`. Do NOT build the APK (Claude ships it).

## Goal
Give parents a simple chat screen to ask the EDIFIS AI about THEIR OWN children
("Why did I get a fee notice?", "How is my son doing this term?"). The backend
endpoint already exists and is fully scoped — you only build the Flutter UI.

## Backend contract (already live)
- `POST /parent/ask`  body: `{ "question": "<text, max 500 chars>" }`
- Auth: the parent's bearer token (use the existing `dioProvider` — it already attaches auth).
- Response: `{ "answer": "<assistant text>" }` (always 200; on any error the answer is a friendly "unavailable" message).

## Build

### 1. A chat screen `lib/features/parent/parent_ask_screen.dart`
- `ConsumerStatefulWidget`. Keep a local `List<({bool fromUser, String text})> _messages`.
- A scrollable message list (user bubbles right/blue, assistant bubbles left/grey — reuse `GlassCard`/`AppColors` styles already in the app).
- A bottom input row: `TextField` + send button (lucide `send` from `lucide_icons_flutter`).
- On send: push the user message, show a "typing…" indicator, `POST /parent/ask` with `{question}`, then push the `answer` from the response. Disable the input while waiting.
- Seed the conversation with one assistant welcome bubble: "Hi! I can answer questions about your children at <school>. Ask me anything — fees, results, attendance." (school name: reuse `meProvider`'s `schoolName` if available, else just "your school").
- Errors: never crash — if Dio throws, show an assistant bubble "Sorry, I couldn't reach the assistant. Please try again."
- Suggest 3 tappable starter chips above the input: "Why do I owe fees?", "How is my child doing?", "Any upcoming events?" — tapping fills/sends the question.

### 2. Route
In `lib/shared/routing/app_router.dart` add `GoRoute(path: '/parent-ask', builder: (c,s) => const ParentAskScreen())` and the import.

### 3. Entry point
On the parent dashboard (`lib/features/parent/parent_dashboard_screen.dart`), add a prominent button/card "Ask EDIFIS AI" (lucide `sparkles` or `messageCircle` icon) → `context.push('/parent-ask')`. Place it near the existing "View Report Card" `GlossyButton`.

## Constraints
- Match existing style: Riverpod, `dioProvider`, `lucide_icons_flutter`, `GlassCard`, `GlossyButton`, `AppColors`.
- No new packages.
- `flutter analyze` must be 0 errors.

## Acceptance
- New chat screen reachable from the parent dashboard.
- Sending a question hits `POST /parent/ask` and renders the `answer`.
- Works offline-soft (network error → friendly bubble, no crash).
- Commit + push, then tell Claude so he builds + ships the APK and end-to-end tests with the live AI.

## Notes
- Demo parent login: identifier `demoparent@pssnkwen.local` / `secret` (login field is `identifier`).
