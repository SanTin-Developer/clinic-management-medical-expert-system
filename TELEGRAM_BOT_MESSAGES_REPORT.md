# Telegram Bot Messages Report

## Objective

Customize every user-facing message the RoyalCare Telegram bot can send so the
wording is professional, helpful, and consistent, then centralize the templates
and lock the behavior in with tests. Security controls, API contracts, and
unrelated modules were left untouched.

## Scope of the audit (files inspected)

- `backend/app/Services/Telegram/TelegramUpdateHandler.php` — the single place bot
  commands (`/start`, `/stop`) are handled for both webhook and polling modes.
- `backend/app/Services/Communication/ConversationService.php` — builds the text
  for staff-message notifications (`telegramText()` + `dispatch*Telegram()`).
- `backend/app/Jobs/SendTelegramMessage.php` — sends the text via the client.
- `backend/app/Services/Telegram/TelegramClient.php` — transport (sends with
  `parse_mode=HTML`).
- `backend/app/Services/Telegram/TelegramLinkService.php` — the linking status
  constants the replies map to.
- `backend/app/Http/Controllers/Api/TelegramController.php` — JSON endpoints
  (app-facing, not chat messages).
- `backend/app/Notifications/*` — confirmed none of them deliver over Telegram.

A repo-wide search (`sendMessage`, `SendTelegramMessage::dispatch`, `telegramText`,
`reminder`, `appointment … notification`, `via(...telegram)`) confirmed the only
Telegram *outbound text* paths are the command handler and the conversation
notification.

## Messages delivered (trigger → message)

All text is defined in `backend/app/Services/Telegram/TelegramMessages.php`.

| Trigger | Before | After |
| --- | --- | --- |
| `/start` with no code | `Welcome to RoyalCare. Open Settings → Telegram in the app to generate a linking code.` | `TelegramMessages::welcome()` — friendly welcome that points to **Settings → Telegram → Link Telegram account** and mentions `/help`. |
| Invalid code | `This linking code is invalid. Please generate a new one from RoyalCare.` | `TelegramMessages::invalidCode()` — explains the code is invalid, asks to copy it exactly, and how to get a fresh one. |
| Expired code | `This linking code has expired. Please generate a new one from RoyalCare (Settings → Telegram).` | `TelegramMessages::expiredCode()` — explains codes are short-lived for security and how to get a new one. |
| Already-used code | `This linking code has already been used. Generate a new one from RoyalCare if you need to re-link.` | `TelegramMessages::usedCode()` — confirms it was used and how to re-link. |
| Chat already bound to another user | `This Telegram account is already linked to another RoyalCare user. Send /stop here first, then try again.` | `TelegramMessages::conflictingAccount()` — explains the conflict and the exact `/stop` → re-link path. |
| Successful linking | `Your RoyalCare account is now linked. You will receive notifications here.` | `TelegramMessages::linkingSuccess($username)` — greets the user by `@username` (if known), confirms connection, and mentions `/stop`. |
| `/stop` on a linked chat | `You have been unlinked and will no longer receive notifications here.` | `TelegramMessages::unlinked()` — confirms unlinking + how to reconnect. |
| `/stop` on an unlinked chat | `This chat is not linked to any RoyalCare account.` | `TelegramMessages::alreadyUnlinked()` — explains the chat is not linked + how to connect. |
| `/help` (new) | *(no reply)* | `TelegramMessages::help()` — lists `/start`, `/stop`, `/help` and the in-app linking path. |
| Any other `/command` (new) | *(no reply)* | `TelegramMessages::help()` |
| Plain chat text (new) | *(no reply)* | *(still no reply — the bot stays quiet so it never spams a conversation).* |
| New staff message notification | `**Sender** in **Title**: body` | `TelegramMessages::newMessage()` — `💬 **Sender** in **Title**` then the body; all dynamic values HTML-escaped. |

The command parser, dedupe (`update_id`), token consumption, expiration,
one-time-use, identity binding, and the `safeReply` error swallowing are
unchanged. The handler now also normalizes `/start@botname` (already supported)
and returns help for unrecognised commands.

## Centralization

New file: `backend/app/Services/Telegram/TelegramMessages.php` — a single
`final` class with one static method per message. It is the only place Telegram
copy lives, so future wording changes are one-line edits. The handler and
`ConversationService` now delegate to it instead of holding inline strings.

The class sends with Telegram's `parse_mode=HTML`; every user-supplied value
(username, sender name, conversation title, message body) is escaped through
`e()` before insertion. Templates never interpolate codes, tokens, secrets,
passwords, or medical data.

## Messages requested but not present (and why)

These message types do **not exist** anywhere in the Telegram integration, so
there was nothing to customize. They were deliberately not invented, to avoid
adding behavior the app does not have:

- **Appointment reminders / appointment status updates over Telegram** — no
  reminder or status notification dispatches to Telegram anywhere in `app/`.
  Appointments are surfaced via the in-app/database + broadcast notifications
  only.
- **"Inactive account" reply** — there is no account-active/deactivated concept
  for users in this codebase (the `is_active` flags are only on disease, medicine,
  symptom, and expert-rule records).
- **Rate-limit reply** — the webhook is protected by the `telegram-webhook`
  limiter (120/min/IP), which returns HTTP 429 to Telegram's servers. Telegram
  does not surface that text to the end user and retries the update, so there is
  no user-facing message to customize. The handler also catches and logs
  `Throwable`s without exposing internal errors to the chat.
- **Temporary server-error reply** — same reasoning; failures are logged, never
  echoed to the chat, to avoid leaking internals.
- **"Code generated / resend guidance" as a standalone message** — code
  generation happens inside the authenticated web app (Settings → Telegram), not
  in the bot. The guidance is instead included in the welcome, invalid, expired,
  used, and conflict messages.

## Tests

Added `backend/tests/Unit/TelegramMessagesTest.php` (7 tests) covering each
template, the escaped-username case, and HTML escaping of notification content.

Extended `backend/tests/Feature/TelegramTest.php` (+6 tests) with:
`successful_linking_replies`, `stop_command_replies_when_chat_is_not_linked`,
`help_command_replies_with_command_list`, `unknown_command_replies_with_help`,
`plain_chat_text_does_not_trigger_a_reply`, and
`new_message_notification_text_is_formatted_and_escaped`. Existing assertions
(`expired`, `invalid`, `already been used`, `already linked`, `Welcome to
RoyalCare`) still hold against the new copy.

Results:

- `php artisan test --filter=Telegram` → **39 passed (110 assertions)**
- `php artisan test` (full suite) → **187 passed (616 assertions)**
- `./vendor/bin/pint` on all changed files → clean

Run them with:

```sh
docker exec clinic_backend php artisan test --filter=Telegram
docker exec clinic_backend php artisan test
```

## Files changed

- `backend/app/Services/Telegram/TelegramMessages.php` — **new**, centralized templates.
- `backend/app/Services/Telegram/TelegramUpdateHandler.php` — use templates; added `/help` and unknown-command handling.
- `backend/app/Services/Communication/ConversationService.php` — notification text now built by `TelegramMessages::newMessage()`.
- `backend/tests/Unit/TelegramMessagesTest.php` — **new**.
- `backend/tests/Feature/TelegramTest.php` — new message-flow tests.

No React pages, non-Telegram Laravel modules, database schemas, permissions, or
Python expert-system code were modified.
