# Telegram Account-Linking Debug Report

Scope: diagnose and fix the Telegram staff account-linking flow (bot
`@royalcare_clinic_bot`) in the RoyalCare Clinic Management System.

Status: **fixed and verified** — all Telegram tests pass against the live bot
and database, and the inbound Telegram path was exercised live. One manual
operator step (sending `/start CODE` from the real Telegram client) remains for
final human confirmation and is listed under *Remaining blockers*.

> Credentials (bot token, webhook secret, linking codes) are intentionally
> redacted throughout this report.

---

## 1. Root cause

**Primary — updates never reached the backend.** The bot had **no webhook
registered** with Telegram and **polling was not running**, so `/start CODE`
messages were queued on Telegram's side and never processed:

- `getWebhookInfo` returned `"url": ""` with `pending_update_count: 9`.
- `.env` had `TELEGRAM_WEBHOOK_ENABLED=false`, so even if Telegram *had*
  delivered to `/api/v1/telegram/webhook`, `TelegramController::webhook()`
  would have returned **403**.
- `APP_URL=http://localhost:8000` meant the admin "Register webhook" button
  built a **non-public, non-HTTPS** URL, which Telegram rejects. There was no
  polling fallback and no artisan command to manage the webhook.

**Secondary — UI never refreshed status.** `TelegramSettingsPanel.jsx` generated
the deep link but never re-fetched `/telegram/status`, and the global React
Query default is `refetchOnWindowFocus: false`. So even after a successful
server-side link, the panel kept showing "Link Telegram account" indefinitely.

**Robustness gaps found during audit** (hardened in this change):

- Token consumption was not atomic (race between validate and update).
- No de-duplication of replayed Telegram updates (`update_id`).
- A Telegram chat already linked to another RoyalCare user was **silently
  reassigned** instead of being rejected.
- All failure modes returned one generic message.
- The webhook shared the 60/min per-user API throttle.

**Non-issues (verified):** the generated linking token *is* Telegram-payload
compatible. `Str::random(48)` yields `[A-Za-z0-9]`, which is a subset of
Telegram's allowed `[A-Za-z0-9_-]{1,64}`. A live `/start` update was observed
carrying a 48-character payload. The bot token is valid (`getMe` → ok) and the
queue worker and Reverb are healthy.

---

## 2. Flow trace (React → Laravel → Telegram → DB → UI)

1. **UI** — `TelegramSettingsPanel.jsx` → `telegramApi.link()` → `POST /api/v1/telegram/link`.
2. **API** — `TelegramController::link()` (authorizes `telegram.connect`) →
   `TelegramLinkService::generateToken()` deletes prior unused tokens for the
   user and stores `hash('sha256', $plain)` only, returns the plaintext once
   plus `https://t.me/<bot>?start=<plain>`.
3. **Deep link** — user opens the link; Telegram sends `/start <plain>` to the bot.
4. **Delivery** — webhook (`POST /api/v1/telegram/webhook`, authenticated by
   `X-Telegram-Bot-Api-Secret-Token`) **or** `telegram:poll` `getUpdates`.
   *(Exactly one mode may be active; Telegram refuses `getUpdates` while a
   webhook exists.)*
5. **Handler** — `TelegramUpdateHandler::handle()` de-dupes by `update_id`,
   parses `/start [payload]` (and `/stop`, `@botname` suffix), then calls
   `TelegramLinkService::consumeToken()`.
6. **Binding** — inside a DB transaction with `lockForUpdate`, validates
   existence / expiry / single-use / conflict, then
   `TelegramConnection::updateOrCreate(['user_id' => token.user_id])`. The
   owner is taken **only** from the server-stored token.
7. **Reply** — the bot replies with a status-specific message.
8. **UI refresh** — the panel polls `/telegram/status` every 4s until the
   backend reports `linked: true`, then clears the pending state and toasts.

---

## 3. Evidence (live, pre-fix)

| Check | Result |
|---|---|
| `getMe` | `ok: true`, username `royalcare_clinic_bot` |
| `getWebhookInfo` | `url: ""`, `pending_update_count: 9`, `allowed_updates: [message, my_chat_member]` |
| Config | `enabled: true`, `webhook_enabled: false`, `webhook_secret` set, ttl 15 min |
| `APP_URL` | `http://localhost:8000` (not public/HTTPS) |
| `QUEUE_CONNECTION` | `database` |
| Container | `queue-worker`, `reverb` RUNNING |
| Laravel log | no Telegram/webhook entries (nothing was ever delivered) |

## 4. Evidence (live, post-fix)

| Check | Result |
|---|---|
| `getUpdates offset=-1` | `ok: true`; latest update `/start`, **48-char payload**, valid chat |
| `telegram:poll --once` | drained queue: `pending_update_count` 9 → **0** |
| Synthetic live probe (real token → handler) | `linked: true`, `verified: true`, chat bound, replay de-duped; test rows rolled back |
| Supervisord | `telegram-poll` spawned and **RUNNING** alongside queue-worker/reverb |

---

## 5. Files inspected

- `backend/app/Http/Controllers/Api/TelegramController.php`
- `backend/app/Services/Telegram/TelegramLinkService.php`, `TelegramClient.php`
- `backend/app/Models/TelegramLinkingToken.php`, `TelegramConnection.php`, `TelegramGroupConfig.php`
- `backend/app/Jobs/SendTelegramMessage.php`
- `backend/app/Services/Communication/ConversationService.php`
- `backend/config/communication.php`, `backend/routes/api.php`, `backend/bootstrap/app.php`
- `backend/database/migrations/2026_10_20_000007..000010_*.php`
- `backend/docker/supervisord.conf`, `backend/Dockerfile`, `docker-compose.yml`
- `frontend/src/components/communication/TelegramSettingsPanel.jsx`, `frontend/src/api/communication.js`, `frontend/src/main.jsx`
- `backend/tests/Feature/TelegramTest.php`

## 6. Files changed

Backend:
- `app/Services/Telegram/TelegramClient.php` — added `getWebhookInfo()`,
  `getUpdates()`, non-destructive `deleteWebhook(bool $dropPending = false)`,
  and a per-call HTTP timeout (long-polling).
- `app/Services/Telegram/TelegramLinkService.php` — atomic, transactional
  `consumeToken()` returning a status (`linked|invalid|expired|used|conflict`);
  rejects a chat already owned by another user instead of silent reassignment.
- `app/Services/Telegram/TelegramUpdateHandler.php` *(new)* — single command
  parser + `update_id` de-duplication shared by webhook and poller.
- `app/Http/Controllers/Api/TelegramController.php` — delegates to the handler;
  webhook registration uses `TELEGRAM_WEBHOOK_URL` and refuses non-HTTPS;
  added admin `unregisterWebhook()`.
- `app/Console/Commands/TelegramPoll.php` *(new)*, `TelegramWebhookSet.php` *(new)*,
  `TelegramWebhookDelete.php` *(new)*.
- `app/Providers/AppServiceProvider.php` — dedicated `telegram-webhook`
  rate limiter (120/min per IP).
- `config/communication.php` — `webhook_url`, `polling_enabled`,
  `poll_timeout`, `poll_sleep`.
- `routes/api.php` — `DELETE /telegram/webhook/register`; webhook uses the new throttle.
- `docker/supervisord.conf` — `telegram-poll` program (`autorestart=unexpected`).
- `.env.example` — documented the new Telegram variables.
- `tests/Feature/TelegramTest.php` — expanded.

Frontend:
- `components/communication/TelegramSettingsPanel.jsx` — polls status after
  generating a link, adds a "Check status" button, clears the pending state and
  toasts on success.

---

## 7. Delivery mode decision

Telegram delivery is **either webhook or polling, never both**.

- **This environment uses polling** (`TELEGRAM_WEBHOOK_ENABLED=false`,
  `TELEGRAM_POLLING_ENABLED=true`) because there is no public HTTPS URL.
  The supervised `telegram:poll` worker is running.
- **Webhook mode** is for production behind a public HTTPS endpoint:
  set `TELEGRAM_WEBHOOK_URL=https://.../api/v1/telegram/webhook`,
  `TELEGRAM_WEBHOOK_ENABLED=true`, then run `php artisan telegram:webhook:set`.

Mutual exclusion is enforced in code: `telegram:poll` exits cleanly (code 0, no
restart) when webhook mode is enabled, and it removes any registered webhook
before listening. The existing webhook was empty, so nothing working was
overwritten.

---

## 8. Required configuration

Already set correctly for this environment:
`TELEGRAM_ENABLED`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_BOT_TOKEN`,
`TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_LINK_TOKEN_TTL`.

New/optional:
`TELEGRAM_POLLING_ENABLED=true`, `TELEGRAM_POLL_TIMEOUT=30`,
`TELEGRAM_POLL_SLEEP=1`, and (webhook mode only) `TELEGRAM_WEBHOOK_URL` +
`TELEGRAM_WEBHOOK_ENABLED=true`.

---

## 9. Security properties verified

- The linking target is derived **only** from the server-stored hashed token;
  a `user_id` supplied in the update payload is ignored (test:
  `linking_binds_to_token_owner_and_ignores_client_supplied_user_id`).
- Tokens are stored as SHA-256 hashes, single-use, TTL-limited (15 min), and
  consumed atomically under a transaction lock.
- Webhook requests require a constant-time secret match
  (`hash_equals`) and the feature flag; otherwise 403.
- A Telegram chat already linked to another user is **rejected**.
- Replayed updates (`update_id`) are processed once.
- No clinic modules, RBAC, authentication, or the Python Expert System were modified.

---

## 10. Actual test results

- `php artisan test --filter=TelegramTest` → **26 passed (72 assertions)**.
- `php artisan test` (full backend suite) → **174 passed (578 assertions)**.
- `vendor/bin/pint` (changed files only) → clean.
- `npm run lint` → 0 errors; `npm run build` → success.

New/updated tests cover: webhook secret enforcement, webhook-disabled 403,
`/start CODE` success, invalid code, expired code, already-used code, replayed
`update_id`, unauthorized binding, duplicate Telegram-account conflict,
`@botname` suffix parsing, payload-format compatibility, `/start` with no code,
single-use `consumeToken`, poller processing, and poller self-disable.

---

## 11. Remaining blockers / manual confirmation

1. **One human step remains:** in the app, open *Settings → Telegram*, click
   *Link Telegram account*, and send the shown `/start CODE` to
   `@royalcare_clinic_bot`. The supervised poller will link the account; the
   panel flips to "Linked" automatically (no reload needed). This step cannot be
   automated because Telegram messages can only originate from the user's own
   account.
2. **Public HTTPS webhook** is still required if webhook mode is preferred over
   polling; polling is active meanwhile.
3. A Telegram bot cannot start a conversation with a user who has not messaged
   it first (expected platform behaviour); the probe's reply to a synthetic chat
   was therefore reported and ignored.
4. Pre-existing `pint` style issues in unrelated, untouched files were left
   as-is to avoid unrelated churn.
