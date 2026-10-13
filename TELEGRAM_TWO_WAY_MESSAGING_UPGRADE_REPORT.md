# Telegram Two-Way Messaging Upgrade — Report

Upgrade of RoyalCare's completed **Option 1** (Telegram notification *replies*)
into **Option 2**: a secure, persistent, two-way messaging system between the
React Web App and the existing Telegram bot.

> Status: **complete** (implementation + tests). Baseline audit is Section 1;
> implementation is Section 2; final architecture/APIs/security/tests is
> Section 3. A manual live-bot smoke checklist is in Section 3.6.

---

## 1. Baseline Audit (Phase 1)

Audit method: static inspection of the Laravel backend, React frontend, Docker
and test configuration (no files modified). Line references are approximate.

### 1.1 Capability matrix

| # | Capability | Status | Evidence |
|---|---|---|---|
| **Conversation model** ||||
| 1 | Staff↔staff direct (`private`) conversations | PASS | `Conversation::TYPE_PRIVATE`, `ConversationService::findOrCreatePrivate` |
| 2 | Group conversations | PASS | `TYPE_GROUP`, `ConversationService::createGroup` |
| 3 | Participants + soft leave (`left_at`) | PASS | `conversation_participants`, `removeParticipant` |
| 4 | Web + Telegram messages in one conversation | PASS | `conversation_messages.source`, `sendTelegramReply` |
| 5 | Notification-reply opens/reuses conversation without duplicates | PASS | `TelegramReplyService` → `sendTelegramReply` |
| 6 | Sender, conversation, body, source, timestamps | PASS | `conversation_messages` |
| 7 | Read status (conversation-level) | PASS | `last_read_message_id`, `unreadCount` |
| 8 | Per-message read receipts (`conversation_message_reads`) | PARTIAL | table+model exist, **never written/read** |
| 9 | Telegram ids as routing metadata only | PASS | `telegram_delivery_logs`, unique inbound key |
| 10 | Archive a conversation | MISSING | no column/route; only leave |
| 11 | Per-recipient web delivery status | MISSING | no field/endpoint |
| **Telegram integration** ||||
| 12 | Account linking (deep link + `/start` + idempotent re-link) | PASS | `TelegramLinkService` |
| 13 | Webhook secret verification + throttle | PASS | `TelegramController::webhook`, `telegram-webhook` limiter |
| 14 | Outbound notification delivery (per-user + group) | PASS | `ConversationService::dispatchUserTelegram` |
| 15 | Inbound **reply-to-notification** → conversation | PASS | `TelegramReplyService`, Option 1 |
| 16 | Update de-duplication | PASS | `update_id` cache + DB unique |
| 17 | List conversations from the bot (`/chats`) | MISSING | only `/start`,`/stop`,`/help` |
| 18 | Select conversation from the bot (`/open`) | MISSING | — |
| 19 | Send free text to a selected conversation via bot | MISSING | plain text only works as a native reply |
| 20 | Inline callback buttons / `answerCallbackQuery` / `reply_markup` | MISSING | not implemented; `allowed_updates` excludes callbacks |
| **APIs / authorization** ||||
| 21 | List conversations, message history (paginated), create, send, read, leave | PASS | `ConversationController` |
| 22 | Policy + Spatie permission checks | PASS | `ConversationPolicy` |
| 23 | Membership check on every read/write | PASS | policy + `hasActiveParticipant` |
| 24 | Delivery-status endpoint | MISSING | `TelegramDeliveryLog` unexposed |
| 25 | Add participants API | PASS (API) / PARTIAL (UI) | API exists, unused in UI |
| **Outbound delivery** ||||
| 26 | Queue outbound Telegram, record message ids | PASS | `SendTelegramMessage` |
| 27 | Retry transient failures (3 tries, backoff) idempotently | PASS | job `$tries`/`$backoff`/`idempotency_key` |
| 28 | Statuses queued/sent/delivered/failed | PARTIAL | `pending/sent/failed`; no queued/delivered (unverifiable) |
| 29 | Notify sender on delivery failure | MISSING | failure only logged |
| 30 | Echo-loop prevention | PASS | sender excluded from outbound |
| **React Web App** ||||
| 31 | Inbox, list, preview, view, composer, sender/time | PASS | `ChatPage.jsx` |
| 32 | Source channel indicator (Telegram) | PASS | `ChatPage.jsx` Telegram badge |
| 33 | Real-time via Reverb/Echo | PASS | `MessageSent` → `conversation.{id}` |
| 34 | Unread indicators (conversation-level) | PASS | list badge + `useChatUnread` |
| 35 | Message history pagination / "load older" | MISSING | only `page: 1` fetched |
| 36 | Optimistic send + sending/sent/failed states | MISSING | appended only after response |
| 37 | Delivery/read receipts in UI | MISSING | — |
| 38 | Notification Center page | MISSING | bell dropdown only |
| **Infrastructure** ||||
| 39 | Reverb + queue workers + telegram-poll supervised | PASS | `supervisord.conf` |
| 40 | Broadcasting private-channel authorization | PASS | `routes/channels.php` + Sanctum |
| 41 | Automated tests for Option 1 | PASS | `TelegramTest` (50), `ConversationTest`, `NotificationApiTest` |

### 1.2 Notable findings
- No archive concept; leave (`left_at`) is the only removal mechanism.
- `conversation_message_reads` is dead schema — read state lives on
  `conversation_participants.last_read_message_id`.
- Telegram delivery logs are written but never exposed via API.
- The bot cannot **initiate** a conversation or send arbitrary text; it can
  only reply via Telegram's native "Reply" to a previously delivered bot
  message.

### 1.3 Blockers
- None for the core upgrade. Public HTTPS webhook is only required if webhook
  mode is preferred over the existing polling worker.

---

## 2. Implementation

All changes are **additive**; no existing tables, columns, endpoints, or
Option 1 behaviours were removed or renamed.

### 2.1 Backend

| File | Change |
|---|---|
| `database/migrations/2026_10_20_000012_add_active_conversation_to_telegram_connections.php` | New nullable FK `telegram_connections.active_conversation_id → conversations.id` (`nullOnDelete`). Integration state only. |
| `app/Models/TelegramConnection.php` | `active_conversation_id` fillable + int cast + `activeConversation()` relation. |
| `app/Models/Conversation.php` | New `displayTitleFor(User)` helper (group title, or the other participant's name) shared by API/controller and bot. |
| `app/Services/Telegram/TelegramConversationService.php` | New. `/chats` listing, `/open <id>` selection, `/close` clear. Membership re-validated on every call; `chat.send` required to open. |
| `app/Services/Telegram/TelegramReplyService.php` | Target resolution now falls back to the connection's `activeConversation` when the inbound message is not a native reply. All Option 1 guards (linked user, active participant, `chat.send`, length, dedupe) unchanged. |
| `app/Services/Telegram/TelegramUpdateHandler.php` | Injects `TelegramConversationService`; handles `/chats`, `/open`, `/close`; free text → selected conversation; distinct confirmations for replies vs. free text; helpful guidance when nothing is selected. |
| `app/Services/Telegram/TelegramMessages.php` | New escaped templates: `conversationList`, `noConversations`, `conversationOpened`, `conversationNotFound`, `openUsage`, `conversationForbidden`, `conversationClosed`, `noActiveConversation`, `messageSent`; `help()` updated. |
| `app/Http/Controllers/Api/ConversationController.php` | New `messageDelivery()` — participants-only, 404 if the message is not in the conversation; returns per-recipient Telegram delivery rows (`recipient_user_id`, `channel`, `status`, `attempts`, `updated_at`). **Does not expose `telegram_chat_id` or error text.** |
| `routes/api.php` | New `GET /api/v1/conversations/{conversation}/messages/{message}/delivery`. |
| `app/Jobs/SendTelegramMessage.php` | `failed()` now notifies the message author (privacy-safe) after all retries are exhausted. |
| `app/Notifications/TelegramDeliveryFailedNotification.php` | New database+broadcast notification (`kind: telegram_delivery_failed`); carries only conversation id + message id, no content. |

### 2.1b Chat UX follow-up (read receipts, group members, composer fix)

| File | Change |
|---|---|
| `app/Events/MessagesRead.php` | New. Broadcasts `messages.read` on `conversation.{id}` when a participant's read marker advances. |
| `app/Services/Communication/ConversationService.php` | `markRead()` now compares the stored marker, updates once, and dispatches `MessagesRead` only when it actually advances. |
| `app/Http/Controllers/Api/ConversationController.php` | `serialize()` now exposes `participants[].last_read_message_id` so clients can compute receipts. |
| `frontend/src/pages/ChatPage.jsx` | **Composer fix**: both `Card` bodies are now flex columns (`flex min-h-0 flex-1 flex-col`) and the grid row is `minmax(0,1fr)`, so the message list really scrolls and the input stays pinned. **Receipts**: own messages show `Delivered` → `Seen` (`Seen by N` in groups when only some have read), updated live via `.messages.read`. **Group members**: header count + a "View members" menu item open a members modal. |
| `tests/Feature/ConversationTest.php` | +2 tests: participant read markers exposed by the show endpoint; read receipt broadcast fired exactly once per advance. |



| File | Change |
|---|---|
| `src/api/communication.js` | `conversationApi.messageDelivery(id, messageId)`. |
| `src/pages/ChatPage.jsx` | "Load older messages" pagination (`current_page < last_page`); optimistic send with `sending → sent/failed` states and a retry action; a "Delivery" action on own web messages that surfaces Telegram delivery status. Auto-scroll only on new newest message (prepending older messages does not jump). |
| `src/components/communication/NotificationBell.jsx` | Renders the new `telegram_delivery_failed` kind. |

### 2.3 Inbound routing precedence

1. Commands (`/start`, `/stop`, `/help`, `/chats`, `/open`, `/close`) always win.
2. Plain text that is a **native Telegram reply** → mapped conversation (Option 1).
3. Plain text with a conversation selected via `/open` → that conversation.
4. Otherwise → guidance; nothing is stored.

No routing decision is ever based on "most recent conversation".

### 2.4 What was intentionally **not** built (documented gaps)

- **Archive** — no archive concept exists; `left_at` (leave) is the only removal.
  Adding an archive would require schema + UI and was out of scope.
- **Per-message read receipts** — `conversation_message_reads` remains unused;
  read state is conversation-level (`last_read_message_id`). Not surfaced.
- **Telegram inline callback buttons** — selection uses `/chats` + `/open <id>`
  commands. This is functional and avoids adding Telegram callback plumbing
  (`answerCallbackQuery`, `editMessageText`, `reply_markup`, `allowed_updates`
  callback support). Documented as a future enhancement.
- **Notification Center page** — the existing bell dropdown was extended; a full
  page was not added.
- **Delivery/read claims** — only verifiable states (`pending/sent/failed` from
  the Telegram API) are shown. Bot API delivery/read receipts are not claimed.

---

## 3. Final Architecture, APIs, Flows, Security, Tests

### 3.1 Data model
- `conversation_messages.source` (`web`/`telegram`) + `telegram_chat_id` /
  `telegram_message_id` identify channel and enable inbound routing.
- `telegram_delivery_logs` maps outbound bot messages to conversations
  (`conversation_id`, `conversation_message_id`) and tracks `status`, `attempts`.
- `telegram_connections.active_conversation_id` stores the bot's current target.

### 3.2 API summary (added)
```
GET /api/v1/conversations/{conversation}/messages/{message}/delivery
  auth: sanctum + chat.view (ConversationPolicy@view)
  200 { data: [ { recipient_user_id, channel, status, attempts, updated_at } ] }
  404 if message ∉ conversation; 403 if not a participant
```
No existing request/response shape changed. `ConversationMessage` serialization
is unchanged.

### 3.3 Example flows
- **Web → Telegram:** staff sends in `ChatPage` → optimistic bubble → POST
  `/conversations/{id}/messages` → `ConversationService::sendMessage` queues
  `SendTelegramMessage` per linked participant → delivery row updated to `sent`.
  Sender can tap **Delivery** to see per-recipient status.
- **Telegram → Web:** staff sends `/open 12` then types → message stored with
  `source=telegram` → `MessageSent` broadcasts on `conversation.12` → other staff
  see it live; confirmation sent back to the chat.
- **Notification reply (Option 1, preserved):** reply to a delivered bot message
  → resolved via `telegram_delivery_logs` with no `/open` needed.
- **Failure:** after 3 attempts the delivery row is `failed` and the author gets a
  `telegram_delivery_failed` notification (no content).

### 3.4 Security / privacy controls
- Webhook protected by secret-token comparison + per-IP throttle; updates
  de-duplicated by `update_id`; inbound messages de-duplicated by
  `(chat_id, message_id)`.
- Every inbound/outbound action re-checks: linked+verified connection, active
  participant, and (for sending) `chat.send`.
- `/chats` and `/open` only ever expose conversations the linked user already
  belongs to; a non-member id yields "not found".
- Patient role cannot link Telegram (existing tests) and holds no chat perms.
- Bot copy is HTML-escaped centrally (`TelegramMessages`); conversation titles
  are escaped.
- Telegram chat ids and error text are **not** returned by the delivery endpoint.
- Chat text is never treated as authorization for clinical actions.

### 3.5 Verification (actually run)
- `docker exec clinic_backend php artisan migrate --force` → migration applied.
- `docker exec clinic_backend php artisan test --filter=Telegram` → **64 passed**
  (`TelegramTest`, incl. 11 new feature tests).
- `docker exec clinic_backend php artisan test --filter=TelegramMessagesTest` →
  **15 passed**.
- `docker exec clinic_backend php artisan test` (full) → **219 passed
  (730 assertions)** (baseline before this task: 201).
- `./vendor/bin/pint --test <changed files>` → PASS.
- `npm run lint` → warnings only (pre-existing); `npm run build` → success.
- `docker restart clinic_backend` → `queue-worker`, `reverb`, `telegram-poll`
  all RUNNING with the new code (verified via `docker logs`).
- `php artisan route:list --path=conversations` → delivery route registered.

### 3.6 Manual smoke checklist (for a human, against the live bot)
1. `/chats` lists only conversations you belong to; current one marked.
2. `/open <id>` confirms; a plain message appears in the web app.
3. `/close` stops routing; a plain message does not persist.
4. `/open <someone-else's-id>` → "could not find".
5. Reply to a notification still works (Option 1 regression).
6. Web send shows optimistically, then "Delivery" lists Telegram statuses.

### 3.7 Remaining / future work
- Archive a conversation (schema + UI).
- Per-message read receipts.
- Telegram inline buttons for selection (nicer UX).
- Public HTTPS webhook if webhook mode is preferred over polling.
- Notification Center page.
- Harden unauthenticated non-JSON requests (500 → 401) noted in prior reports.

