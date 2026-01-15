# LMS Notification System – Frontend Integration Guide

This guide explains how to integrate the notification system in your SPA frontend (e.g., Vue, React) using Laravel Echo and the provided API endpoints.

---

## 1. API Endpoints

- **List notifications:**
  - `GET /api/notifications?per_page=20` (paginated, newest first)
  - Optional: `?unread_only=1` to fetch only unread
- **Unread count:**
  - `GET /api/notifications/unread-count`
- **Mark as read (single):**
  - `POST /api/notifications/mark-read/{id}`
- **Mark as read (bulk):**
  - `POST /api/notifications/mark-read` with `{ ids: [1,2,3] }`
- **Create announcement (super_admin only):**
  - `POST /api/notifications/announcements` with `{ title, message, scope: 'all'|'branches', branch_ids? }`

All endpoints require authentication (Bearer token via Sanctum).

---

## 2. Realtime Notifications (Laravel Echo)

### a. Setup Echo
- Use the same Echo config as for chat (Pusher, auth headers, etc).
- Example (JS):

```js
import Echo from 'laravel-echo';
window.Pusher = require('pusher-js');

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: 'your-pusher-key',
  cluster: 'your-pusher-cluster',
  forceTLS: true,
  authEndpoint: '/broadcasting/auth',
  auth: {
    headers: {
      Authorization: `Bearer ${yourApiToken}`,
    },
  },
});
```

### b. Subscribe to Notification Channel
- After login, subscribe to the user's private notification channel:

```js
const userId = currentUser.id;
window.Echo.private(`notifications.${userId}`)
  .listen('.notification.created', (e) => {
    // e contains the notification payload
    // Example: show toast, update badge, or refetch list
    // e = { id, user_id, type, title, message, data, is_read, read_at, created_at, updated_at }
  });
```

---

## 3. UI Suggestions

- **Notification List:**
  - Paginate using `/api/notifications`.
  - Show `title`, `message`, and optionally parse `data` for context (e.g., chat, procurement, announcement).
  - Highlight unread notifications.
- **Unread Badge:**
  - Use `/api/notifications/unread-count` to show badge.
  - Increment badge on `.notification.created` event.
  - Decrement or refetch after marking as read.
- **Mark as Read:**
  - On click, call `POST /api/notifications/mark-read/{id}`.
  - For bulk, collect IDs and call `POST /api/notifications/mark-read`.
- **Announcement Creation (Super Admin):**
  - Use the announcement endpoint with `scope` and optional `branch_ids`.

---

## 4. Notification Types & Data

- **Chat:**
  - `type: 'chat'`
  - `data: { chat_id, message_id, sender_id, sender_username }`
  - Action: Open chat window, scroll to message.
- **Procurement:**
  - `type: 'procurement'`
  - `data: { procurement_id, status, title }`
  - Action: Open procurement details.
- **Announcement:**
  - `type: 'announcement'`
  - `data: { created_by, created_by_name, scope, branch_ids }`
  - Action: Show announcement modal or banner.

---

## 5. Example Notification Payload

```json
{
  "id": 123,
  "user_id": 5,
  "type": "chat",
  "title": "New chat message",
  "message": "New message from admin1",
  "data": {
    "chat_id": 7,
    "message_id": 42,
    "sender_id": 2,
    "sender_username": "admin1"
  },
  "is_read": false,
  "read_at": null,
  "created_at": "2025-12-27T10:00:00Z",
  "updated_at": "2025-12-27T10:00:00Z"
}
```

---

## 6. Tips
- Always use Bearer tokens for API and Echo auth.
- Use the notification event to update UI instantly (badge, list, toast, etc).
- For best UX, debounce or batch mark-as-read API calls if marking many at once.
- Announcements are sent to all or selected branches; check `data.scope` and `data.branch_ids`.

---

For any backend changes, see the backend README or contact the backend team.
