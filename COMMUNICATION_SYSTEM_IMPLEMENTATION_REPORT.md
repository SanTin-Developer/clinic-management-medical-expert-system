# RoyalCare Staff Communication System — Implementation Report

**Feature:** Real-time staff communication (private & group chat, in-app notifications, per-staff Telegram linking, admin-configured Telegram group notifications).
**Stack:** Laravel 13 / PHP 8.4 · Laravel Reverb (websockets) · Laravel Notifications (database + broadcast) · Redis queue · React 19 / Vite 8 (frontend) · PostgreSQL 18 · Docker Compose.
**Status:** Implemented and verified. Backend suite **160 passed (536 assertions)**; frontend `lint` 0 errors and `build` succeeds; Reverb + queue worker run under supervisord and Reverb listens on the published port.

---

## 1. Scope delivered

| Capability | Status |
|---|---|
| Direct (1:1) staff conversations, deduplicated per pair | Done |
| Group conversations (create, add/remove participants, leave) | Done |
| Real-time message delivery over Laravel Reverb websockets | Done |
| Per-conversation unread counts + read receipts (`conversation_message_reads`) | Done |
| In-app notification centre (list, unread count, mark one/all, delete) | Done |
| Per-user notification preferences (new message, group, announcements, Telegram mirror) | Done |
| Telegram account linking via one-time deep-link token + webhook `/start` / `/stop` | Done |
| Admin-configured Telegram **group** notifications by event category | Done |
| Reliable delivery (queued jobs, 3 tries, backoff, delivery log + idempotency) | Done |
| RBAC enforced server-side | Done |
| Docker: Reverb server + queue worker + published websocket port | Done |
| Automated tests (chat, notifications, Telegram) | Done — 30 new tests |
| Frontend: chat UI, notification bell, settings panels, nav + routing | Done |

Out of scope / not modified: Python expert system, medical business logic, existing patient/appointment/billing contracts.

---

## 2. Architecture

```
Browser (React)
 ├─ axios  →  Laravel REST API (/api/v1)        :8000
 └─ laravel-echo + pusher-js (Reverb protocol)  ws://localhost:8080
                 │  POST /broadcasting/auth (Sanctum bearer)  →  :8000
                 ▼
Laravel (php-fpm + nginx + supervisord)
 ├─ REST controllers → Services → Eloquent (PostgreSQL)
 ├─ Notifications (database + broadcast)  ┐
 ├─ MessageSent event (broadcast)         ┘→ Reverb (websocket server) :8080
 ├─ Queued jobs (redis queue) → SendTelegramMessage → Telegram Bot API
 └─ Telegram webhook (public, secret header) → link/unlink
```

- **Transport:** Laravel Reverb speaks the Pusher protocol; the frontend uses `laravel-echo` with `broadcaster: 'reverb'` and `pusher-js`.
- **Private channel auth:** `POST /broadcasting/auth` is wired with the `auth:sanctum` middleware via `->withBroadcasting(...)` in `bootstrap/app.php` (verified with `php artisan route:list --path=broadcasting -v`).
- **Queue:** `QUEUE_CONNECTION=redis` in Docker (compose override); `database` locally. `phpunit.xml` forces `sync` for deterministic tests.

---

## 3. Database schema (10 migrations)

`backend/database/migrations/2026_10_20_000001..000010_*.php`

| # | Table | Purpose |
|---|---|---|
| 000001 | `conversations` | `type` (private/group), `title`, `created_by`, timestamps |
| 000002 | `conversation_messages` | `conversation_id`, `sender_id`, `body` |
| 000003 | `conversation_participants` | membership + `left_at` (soft leave), joins `users` |
| 000004 | `conversation_message_reads` | per-user last-read message markers |
| 000005 | `notifications` | Laravel database notifications (UUID) |
| 000006 | `notification_preferences` | per-user delivery toggles |
| 000007 | `telegram_connections` | `user_id`, `telegram_chat_id`, `telegram_username`, `verified_at` |
| 000008 | `telegram_linking_tokens` | hashed single-use tokens + `expires_at`/`used_at` |
| 000009 | `telegram_group_configs` | singleton group config: `chat_id`, `is_enabled`, `event_categories` |
| 000010 | `telegram_delivery_logs` | delivery audit + idempotency key + status |

All ten were applied successfully (`php artisan migrate --force` → all `DONE`).

---

## 4. Models

`backend/app/Models/`: `Conversation`, `ConversationParticipant`, `ConversationMessage`, `ConversationMessageRead`, `NotificationPreference`, `TelegramConnection`, `TelegramLinkingToken`, `TelegramGroupConfig`, `TelegramDeliveryLog`.

Notable:
- `Conversation::isGroup()`, `hasActiveParticipant(User)`, `users()` / `activeParticipants()` / `messages()`.
- `NotificationPreference::forUser($user)` (creates with explicit defaults), `allows($category)`.
- `TelegramGroupConfig::current()` (singleton) and `CATEGORY_*` constants.
- `User` gains relations (`conversations`, `conversationParticipants`, `sentMessages`, `notificationPreference`, `telegramConnection`, `telegramLinkingTokens`, `telegramDeliveryLogs`) and **`receivesBroadcastNotificationsOn(?object $notification = null): string`** returning `notifications.{id}`.

**Framework note:** in this Laravel version `Illuminate\Notifications\Notification::broadcastOn()` takes no parameters and there is no `broadcastAs()` on the base class, so the per-user broadcast channel is resolved through `User::receivesBroadcastNotificationsOn()`. Overriding `broadcastOn()`/`broadcastAs()` with parameters is a fatal error here.

---

## 5. RBAC

Added to `RolePermissionSeeder` and applied (`php artisan db:seed --class=RolePermissionSeeder --force`):

- `chat.view`, `chat.create`, `chat.send`, `chat.manage`
- `notifications.view`, `notifications.manage`
- `telegram.connect`, `telegram.group.manage`

Granted to **Receptionist, Nurse, Doctor, Pharmacist**; **Admin** receives all. **Patients** receive none (verified by tests). Server-side enforcement is via `ConversationPolicy` and inline `can()` checks in the notification/Telegram controllers.

---

## 6. REST API (`/api/v1`, Sanctum-protected unless noted)

### Conversations — `ConversationController`
| Method | Path | Notes |
|---|---|---|
| GET | `/conversations` | Paginated list for the viewer, with `unread_count` and `last_message` |
| GET | `/conversations/recipients` | Staff only (excludes Patients), for the picker |
| POST | `/conversations` | `type=private` (`user_id`) or `type=group` (`title`,`participant_ids`) |
| GET | `/conversations/{id}` | |
| GET | `/conversations/{id}/messages` | Paginated, newest first |
| POST | `/conversations/{id}/messages` | `body` (max from `config('communication.chat.max_message_length')`) |
| POST | `/conversations/{id}/read` | Optional `message_id` |
| POST | `/conversations/{id}/participants` | Group manage |
| DELETE | `/conversations/{id}/participants/{user}` | Manage or self (leave) |

### Notifications — `NotificationController`
`GET /notifications`, `GET /notifications/unread-count`, `POST /notifications/read-all`, `POST /notifications/{id}/read`, `DELETE /notifications/{id}`, `GET /notification-preferences`, `PUT /notification-preferences`.

### Telegram — `TelegramController`
`GET /telegram/status`, `POST /telegram/link`, `DELETE /telegram/link`, `POST /telegram/webhook/register` (admin), `GET|PUT /telegram/group-config` (admin), and **public** `POST /telegram/webhook` (throttled; validated by `X-Telegram-Bot-Api-Secret-Token`).

---

## 7. Real-time layer

- **`app/Events/MessageSent.php`** — `ShouldBroadcast` on `PrivateChannel('conversation.{id}')`, `broadcastAs()` = `message.sent`.
- **`routes/channels.php`** — `conversation.{conversation}` (active participant), `notifications.{user}` (self), plus the default `App.Models.User.{id}`.
- **Notifications broadcast** on `notifications.{user}` with event `.Illuminate\Notifications\Events\BroadcastNotificationCreated` and payload `kind` (`new_message`, etc.) + `url`.
- **Config:** `config/broadcasting.php` and `config/reverb.php` published; `BROADCAST_CONNECTION=reverb`.

---

## 8. Telegram workflow

1. Staff clicks **Link Telegram account** → `POST /telegram/link` → `TelegramLinkService::generateToken()` returns a `deep_link` (`https://t.me/<bot>?start=<token>`) + `expires_at`. The plaintext is shown once; only a SHA-256 hash is stored.
2. User presses Start → Telegram calls the webhook → `/start <token>` → `consumeToken()` verifies hash + expiry, binds `telegram_connections`, and replies.
3. `/stop` unlinks.
4. On each new message, `ConversationService` dispatches `SendTelegramMessage` jobs to linked participants **and** (if enabled) to the admin group chat, honoring notification preferences and group categories. Each delivery is written to `telegram_delivery_logs` with an idempotency key.

---

## 9. Reliability

`app/Jobs/SendTelegramMessage.php`: `tries = 3`, `backoff = [10, 30]`, logs every attempt, and skips duplicate deliveries via the idempotency key. Telegram HTTP calls go through `TelegramClient` (configurable base URL + timeout).

---

## 10. Frontend

New files under `frontend/src/`:
- `lib/echo.js` — Reverb client (lazy singleton, Sanctum authorizer via `POST /broadcasting/auth`, `disconnectEcho()` on logout).
- `api/communication.js` — `conversationApi`, `notificationApi`, `telegramApi`.
- `hooks/useChatUnread.js` — total unread for the sidebar badge.
- `components/communication/NotificationBell.jsx` — bell dropdown with unread badge, live updates via the private notifications channel.
- `components/communication/NewConversationModal.jsx` — direct/group picker.
- `components/communication/NotificationPreferencesPanel.jsx` — preference toggles.
- `components/communication/TelegramSettingsPanel.jsx` — link/unlink, deep link + token copy, admin group config + webhook registration.
- `pages/ChatPage.jsx` — responsive two-pane chat (list + thread, unread badges, real-time append, enter-to-send, leave, group badges).

Wiring: routes `/chat` and `/chat/:id` (guarded by `chat.view`), sidebar "Team Chat" entry with unread badge, `Header` gains the bell, `Settings` gains a **Communication** tab (shown when the user can view notifications / link Telegram), page title mapping added, and Echo is disconnected on logout.

Env: `frontend/.env` gains `VITE_REVERB_APP_KEY/HOST/PORT/SCHEME` (mirrored in `.env.example`).

---

## 11. Docker

- `backend/docker/supervisord.conf` — added `[program:queue-worker]` (`queue:work --sleep=1 --tries=3 --max-time=3600`) and `[program:reverb]` (`reverb:start --host=0.0.0.0 --port=8080`), both run as `www-data` with `stopasgroup`/`killasgroup`.
- `backend/Dockerfile` — added the **`pcntl`** extension (required by Reverb for signal constants) and `EXPOSE 8080`.
- `docker-compose.yml` — backend publishes `"${REVERB_PORT:-8080}:8080"` and sets `REVERB_SERVER_HOST/PORT`.
- Root `.env` / `.env.example` — added `REVERB_PORT=8080`.

**Important:** `supervisord.conf` is baked into the image, so applying these changes requires a rebuild:
`docker compose up -d --build backend`.

---

## 12. Verification (what was actually run)

| Check | Command | Result |
|---|---|---|
| Full backend suite | `docker exec clinic_backend php artisan test` | **160 passed (536 assertions)**, 127.79s |
| Chat tests | `ConversationTest` | 11 passed |
| Notification tests | `NotificationApiTest` | 7 passed |
| Telegram tests | `TelegramTest` | 12 passed |
| Frontend lint | `npm run lint` (oxlint) | 0 errors (pre-existing warnings only) |
| Frontend build | `npm run build` | Success; `ChatPage` and `SettingsPage` chunks emitted |
| Compose validity | `docker compose config --quiet` | OK |
| Reverb runtime | `docker exec clinic_backend ss -ltn` | Listening `0.0.0.0:8080`; host map `8080 -> 8080` |
| Reverb stability | `docker logs clinic_backend` | `success: reverb entered RUNNING`; no restarts after `pcntl` added |
| Queue worker | `docker logs clinic_backend` | `success: queue-worker entered RUNNING` |

Baseline before this feature was 130 passing tests; the feature adds 30. Full suite total is 160.

### Fixed during verification
- Reverb initially crash-looped with `Undefined constant "...SIGINT"` → root cause: missing `pcntl` extension → added to the image; Reverb then stayed up.

---

## 13. Manual / environment-dependent verification still recommended

These require a browser and/or live Telegram and were **not** executed end-to-end automatically:
- Browser real-time round-trip (two logged-in staff, messages appearing without refresh; bell badge updating).
- Live Telegram link via the real bot (`@royalcare_clinic_bot`) and a real `/start` / `/stop`.
- Admin group delivery to a real Telegram group.
- Non-interactive `php artisan reverb:install` prompts were bypassed; `.env` was configured manually and is the source of truth.

Recommended manual smoke test: `docker compose up -d`, open `http://localhost:5173`, log in as `admin@clinic.local` / `Admin@12345`, open **Team Chat**, start a direct conversation with a staff account in a second browser, and confirm live delivery, unread badge, notification bell, and (optionally) Telegram linking.

---

## 14. Security notes

- Chat/notification/Telegram endpoints are Sanctum-protected; private-channel subscriptions are authorized server-side (`routes/channels.php`).
- Telegram webhook is public but rejects any request missing the exact `X-Telegram-Bot-Api-Secret-Token` (`hash_equals`), and is rate-limited.
- Linking tokens are single-use, time-boxed (default 15 min), and stored only as SHA-256 hashes.
- Patients are denied all communication abilities.
- Secrets (bot token, webhook secret, Reverb secret, Cloudinary secret) live in `.env` only; `.env.example` uses placeholders. **They are not committed.**

---

## 15. Files added / changed (high level)

**Added (backend):** 10 migrations; 9 models; `Services/Communication/ConversationService.php`; `Services/Telegram/{TelegramClient,TelegramLinkService}.php`; `Jobs/SendTelegramMessage.php`; `Policies/ConversationPolicy.php`; `Http/Controllers/Api/{ConversationController,NotificationController,TelegramController}.php`; `Events/MessageSent.php`; `Notifications/{NewMessageNotification,SystemAnnouncementNotification}.php`; `config/communication.php`; tests `ConversationTest`, `NotificationApiTest`, `TelegramTest`.

**Changed (backend):** `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, `database/seeders/RolePermissionSeeder.php`, `routes/api.php`, `routes/channels.php`, `bootstrap/app.php`, `phpunit.xml`, `.env`, `.env.example`, `docker/supervisord.conf`, `Dockerfile`, `docker-compose.yml`.

**Added (frontend):** `src/lib/echo.js`, `src/api/communication.js`, `src/hooks/useChatUnread.js`, `src/pages/ChatPage.jsx`, `src/components/communication/{NotificationBell,NewConversationModal,NotificationPreferencesPanel,TelegramSettingsPanel}.jsx`, `.env` / `.env.example` websocket vars.

**Changed (frontend):** `src/router/index.jsx`, `src/components/layout/{Header,Sidebar}.jsx`, `src/pages/SettingsPage.jsx`, `src/hooks/usePageTitle.js`, `src/context/AuthProvider.jsx`, `package.json` (+`laravel-echo`, `pusher-js`).

---

## 16. Known limitations / follow-ups

- A dark-launch/feature-flag toggle for the whole communication module was not added; visibility is controlled entirely by RBAC permissions.
- Group management UI is minimal (create + leave); participant add/remove are exposed by the API but not yet surfaced as a full management screen.
- Message pagination is provided by the API; the UI currently loads the first page of messages (infinite scroll is a follow-up).
- The initial JS bundle includes `pusher-js` because the notification bell is always mounted for authenticated users.
