# Telegram Two-Way Replies — Implementation Report

## 1. Objective

Allow RoyalCare staff to **reply to Telegram notifications** and have those
replies:

* stored in Laravel as real conversation messages,
* visible in the React web app on the correct conversation, and
* broadcast to other participants in real time.

The feature reuses the existing communication architecture (conversations,
notification pipeline, Telegram integration) rather than introducing a parallel
system.

## 2. Architecture (as-built)

```
Web staff message ──► ConversationService::sendMessage
                          │
                          ├─ MessageSent (Reverb broadcast, conversation.{id})
                          └─ NewMessageNotification + SendTelegramMessage
                                                                 │
                                                                 ├─ TelegramClient::sendMessage → message_id
                                                                 └─ telegram_delivery_logs row
                                                                    (chat_id, message_id, conversation_id,
                                                                     conversation_message_id)

Staff replies in Telegram ──► TelegramUpdateHandler::handle
                                   │
                                   └─ TelegramReplyService::handle
                                          │ resolve delivery-log mapping by (chat_id, reply_to.message_id)
                                          │ verify linked user + active participant + chat.send
                                          │ dedupe on (chat_id, inbound message_id)
                                          └─ ConversationService::sendTelegramReply
                                                 └─ conversation_messages row (source = telegram)
```

## 3. Schema changes

Single additive migration:
`backend/database/migrations/2026_10_20_000011_add_telegram_two_way_reply_columns.php`

### `conversation_messages` (extended — reuse existing table)
| Column | Type | Notes |
| --- | --- | --- |
| `source` | string(20), default `web` | `web` or `telegram` |
| `telegram_chat_id` | bigInteger, nullable | inbound origin |
| `telegram_message_id` | bigInteger, nullable | inbound Telegram id |

* Unique `(telegram_chat_id, telegram_message_id)` → one inbound Telegram
  message can never be stored twice.

### `telegram_delivery_logs` (extended — outbound mapping/audit)
| Column | Type | Notes |
| --- | --- | --- |
| `telegram_message_id` | bigInteger, nullable, indexed | set from Telegram's `result.message_id` |
| `conversation_id` | FK → `conversations`, nullOnDelete | application record |
| `conversation_message_id` | FK → `conversation_messages`, nullOnDelete | internal notification identifier |

The existing delivery log already recorded `telegram_chat_id`, `user_id`,
`event_type`, `subject` and `idempotency_key`, so it is the natural place to
associate an outbound notification with its application record using the actual
Telegram chat + message ids.

No existing columns or tables were dropped or renamed.

## 4. Files changed / added

### Added
* `backend/app/Services/Telegram/TelegramReplyService.php` — inbound reply
  resolution, authorisation flags and idempotency.
* `backend/database/migrations/2026_10_20_000011_add_telegram_two_way_reply_columns.php`.

### Changed
* `backend/app/Jobs/SendTelegramMessage.php` — captures `result.message_id`,
  accepts `conversationId` / `conversationMessageId`, records them on the log.
* `backend/app/Services/Communication/ConversationService.php` — private
  `createMessage()` (source-aware); new `sendTelegramReply()`; both Telegram
  dispatchers now pass the conversation/message ids.
* `backend/app/Services/Telegram/TelegramUpdateHandler.php` — routes non-command
  text to reply handling; commands still take precedence; sends guidance via
  `safeReply`.
* `backend/app/Services/Telegram/TelegramMessages.php` — new templates:
  `replyRecorded`, `replyNotRecognized`, `replyNotLinked`, `replyUnauthorized`,
  `replyEmpty`, `replyTooLong`.
* `backend/app/Models/ConversationMessage.php` — `source` constants + fields,
  casts, `isFromTelegram()`.
* `backend/app/Models/TelegramDeliveryLog.php` — new fields, casts, relations.
* `backend/app/Events/MessageSent.php` — broadcast payload now includes `source`.
* `backend/app/Http/Controllers/Api/ConversationController.php` —
  `serializeMessage()` now returns `source`.
* `frontend/src/pages/ChatPage.jsx` — normalises `source` and renders a
  **Telegram** badge on inbound replies.
* `backend/tests/Feature/TelegramTest.php` — 9 new tests + 2 replaced.
* `backend/tests/Unit/TelegramMessagesTest.php` — 3 new tests.

## 5. Security & authorisation

An inbound reply is accepted **only** when every check passes:

1. The Telegram chat is bound to a **verified** `TelegramConnection`
   (`verified_at` not null). Unlinked chats get `replyNotLinked()`.
2. The message is a Telegram **reply** (`reply_to_message.message_id` present).
3. A `telegram_delivery_logs` row exists with that `(chat_id, message_id)` and a
   non-null `conversation_id`.
4. The linked user is an **active participant** of that conversation
   (`Conversation::hasActiveParticipant`) **and** holds the `chat.send`
   permission.
5. The body is non-empty after trimming and within
   `communication.chat.max_message_length` (4000).
6. The inbound `(chat_id, message_id)` has not already been stored.

Identities are resolved exclusively server-side from the delivery-log mapping;
no user-supplied conversation id, user id or permission claim is trusted.
A reply never performs a privileged or medical action — it only appends a chat
message through the same service the web UI uses.

Failure responses never echo the referenced conversation's contents; they only
state whether the reply could be matched / authorised / stored.

## 6. Behaviour changes

* Plain, non-reply chat text is no longer silently ignored. Depending on state
  it now receives one of: link guidance, "could not match", or — when the
  message is a valid reply — a confirmation. This is intentional so users learn
  how to reply. Command handling (`/start`, `/stop`, `/help`) is unchanged.
* `telegram_delivery_logs` now carries the Telegram message id and conversation
  linkage for every successful send (group and direct).

### Not supported (by design)
* **Group-chat replies.** Telegram group chats are not `TelegramConnection`s
  (they represent no verified user), so replies from a group return
  `replyNotLinked`. The bot still *sends* group notifications.
* Replies to notifications whose delivery log was pruned/never sent (e.g. a
  failed delivery) are treated as "could not match".

## 7. Tests & verification

```
docker exec clinic_backend php artisan test --filter=Telegram
  → 50 passed (147 assertions)

docker exec clinic_backend php artisan test
  → 201 passed (664 assertions)

docker exec clinic_backend ./vendor/bin/pint <changed files>
  → clean

cd frontend; npm run lint   → warnings only (pre-existing), no errors
cd frontend; npm run build  → ✓ built
```

New coverage: valid reply stored + confirmation; unlinked chat rejected;
non-participant rejected; unknown notification rejected; duplicate inbound
stored once; over-long rejected; empty rejected; reply exposed via the messages
API with `source=telegram`; outbound delivery records the message mapping; plus
unit coverage of the new templates (escaping and wording).

Live environment: migration applied with `php artisan migrate --force`
(additive). `clinic_backend` restarted; `queue-worker`, `reverb` and
`telegram-poll` all report RUNNING with the new code.

## 8. Manual verification (staff)

1. Link a Telegram account (Settings → Telegram).
2. From another staff account, send a direct message to that user in the app.
3. In Telegram, **reply** to the notification message and type a response.
4. Expect a "✅ Your reply was sent…" confirmation in Telegram.
5. In the app, the conversation shows the message with a **Telegram** badge,
   attributed to the linked user, delivered in real time to other participants.

## 9. Known limitations

* Only text replies are handled; photos/files/captions are ignored.
* Group-chat replies are intentionally not mapped to a user.
* A reply's confirmation is sent synchronously via the Telegram API; transient
  API failures are reported but do not block message storage.
