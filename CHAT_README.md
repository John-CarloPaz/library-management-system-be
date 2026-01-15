# 💬 Admin 1‑on‑1 Chat API – Frontend Guide

This guide explains how to use the **Admin 1‑on‑1 Chat API** from your frontend (Vue/React/etc.) and how to hook it up to **real‑time updates via Pusher + Laravel Echo**.

The chat module only supports **direct 1‑on‑1 messaging between admins**:
- Only users with roles `super_admin`, `branch_admin`, or `admin` can use chat.
- Each chat always has **exactly two participants**.

---

## 1. Backend Prerequisites

- Database migrated:
  - `php artisan migrate`
- Admin authentication works and returns a Sanctum token (`/api/users/login`).
- Broadcasting configured with Pusher in `.env`:

```env
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=your-cluster
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
```

- Chat endpoints are registered under `/api/chat/...` and protected by `auth:sanctum` and your existing IP/branch middleware.

> All requests below must include a **Bearer token** for an authenticated admin.

---

## 2. HTTP API – 1‑on‑1 Only

Base URL (dev example):

```text
http://127.0.0.1:8000/api
```

### 2.1 List My Chats

**GET** `/api/chat/chats`

Returns all 1‑on‑1 chats where the current admin is a participant.

Response (simplified):

```json
{
  "chats": [
    {
      "id": 1,
      "name": null,
      "is_group": false,
      "created_by": 1,
      "users": [
        { "id": 1, "username": "superadmin" },
        { "id": 2, "username": "admin2" }
      ],
      "messages": [
        {
          "id": 10,
          "message": "Latest message...",
          "created_at": "2025-12-23T10:00:00.000000Z"
        }
      ]
    }
  ]
}
```

### 2.2 Open or Create a 1‑on‑1 Chat

**POST** `/api/chat/chats`

Body:

```json
{
  "recipient_id": 2
}
```

Behavior:
- If a **1‑on‑1 chat between the current admin and `recipient_id` already exists**, it is returned.
- Otherwise, a new 1‑on‑1 chat is created.
- Self‑chat is rejected (you cannot set `recipient_id` equal to your own ID).
- `recipient_id` must be an admin (`super_admin`, `branch_admin`, or `admin`).

Response (simplified):

```json
{
  "message": "1-on-1 chat ready.",
  "chat": {
    "id": 5,
    "name": null,
    "is_group": false,
    "created_by": 1,
    "users": [
      { "id": 1, "username": "superadmin" },
      { "id": 2, "username": "admin2" }
    ]
  }
}
```

### 2.3 Load Messages for a Chat

**GET** `/api/chat/chats/{chatId}/messages?per_page=25`

- Only participants (the two admins in the chat) are allowed to access this endpoint.

Response (simplified):

```json
{
  "chat": {
    "id": 5,
    "users": [
      { "id": 1, "username": "superadmin" },
      { "id": 2, "username": "admin2" }
    ]
  },
  "messages": {
    "data": [
      {
        "id": 100,
        "chat_id": 5,
        "user_id": 1,
        "message": "Hello",
        "created_at": "2025-12-23T10:05:00.000000Z",
        "user": {
          "id": 1,
          "username": "superadmin",
          "first_name": "Super",
          "last_name": "Admin"
        }
      }
    ],
    "current_page": 1,
    "last_page": 4
  }
}
```

### 2.4 Send a Message

**POST** `/api/chat/chats/{chatId}/messages`

Body:

```json
{
  "message": "Hello"
}
```

Response:

```json
{
  "message": "Message sent successfully.",
  "chat_message": {
    "id": 101,
    "chat_id": 5,
    "user_id": 1,
    "message": "Hello",
    "created_at": "2025-12-23T10:06:00.000000Z",
    "user": {
      "id": 1,
      "username": "superadmin",
      "first_name": "Super",
      "last_name": "Admin"
    }
  }
}
```

Each successful send also broadcasts a real‑time event via Pusher.

---

## 3. Real‑time Pusher + Laravel Echo

### 3.1 Install Frontend Dependencies

In your **frontend** project:

```bash
npm install laravel-echo pusher-js
```

### 3.2 Echo Configuration Example

`echo.js` (or similar):

```js
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

export const echo = new Echo({
  broadcaster: "pusher",
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  wsHost: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
  wsPort: import.meta.env.VITE_PUSHER_PORT || 6001,
  wssPort: import.meta.env.VITE_PUSHER_PORT || 443,
  forceTLS: true,
  encrypted: true,
  disableStats: true,
  authEndpoint: "http://127.0.0.1:8000/broadcasting/auth",
  auth: {
    headers: {
      Authorization: `Bearer ${localStorage.getItem("token")}`,
      Accept: "application/json",
    },
  },
});
```

Adjust URLs/env vars to match your setup.

### 3.3 Channel & Event Names

On the backend:
- Private channel: `chat.{chatId}`
- Event class: `ChatMessageSent`
- Event name on frontend: `.chat.message.sent`

Payload shape in the listener (simplified):

```json
{
  "id": 101,
  "chat_id": 5,
  "user_id": 1,
  "message": "Hello",
  "read_at": null,
  "created_at": "2025-12-23T10:06:00.000000Z",
  "user": {
    "id": 1,
    "username": "superadmin",
    "first_name": "Super",
    "last_name": "Admin"
  }
}
```

### 3.4 Subscribing to a 1‑on‑1 Chat

```js
import { echo } from "./echo";

export function subscribeToChat(chatId, onMessage) {
  return echo
    .private(`chat.${chatId}`)
    .listen(".chat.message.sent", (event) => {
      onMessage(event);
    });
}
```

Usage example (pseudo‑code):

```js
const unsubscribe = subscribeToChat(activeChatId, (message) => {
  messages.value.push(message);
});

// When leaving the chat:
// echo.leave(`chat.${activeChatId}`);
```

---

## 4. Typical Frontend Flow

### 4.1 Login & Store Token

1. Call `/api/users/login` with email/password.
2. Store returned `token`.
3. Configure your HTTP client and Echo to use `Authorization: Bearer <token>`.

```js
import axios from "axios";

axios.defaults.baseURL = "http://127.0.0.1:8000/api";

export function setAuthToken(token) {
  axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
  localStorage.setItem("token", token);
}
```

### 4.2 List Chats

```js
const { data } = await axios.get("/chat/chats");
const chats = data.chats; // show as conversations list
```

### 4.3 Start / Open a 1‑on‑1 Chat

```js
// recipientId is the other admin's user ID
const { data } = await axios.post("/chat/chats", {
  recipient_id: recipientId,
});

const chat = data.chat;
```

### 4.4 Load Messages & Subscribe

```js
async function openChat(chatId) {
  const { data } = await axios.get(`/chat/chats/${chatId}/messages`, {
    params: { per_page: 25 },
  });

  activeChat.value = data.chat;
  messages.value = data.messages.data.reverse(); // newest last in UI

  subscribeToChat(chatId, (newMessage) => {
    messages.value.push(newMessage);
  });
}
```

### 4.5 Send a Message

```js
async function sendMessage(chatId, text) {
  if (!text.trim()) return;

  const { data } = await axios.post(`/chat/chats/${chatId}/messages`, {
    message: text,
  });

  // optimistic update
  messages.value.push(data.chat_message);
}
```

---

## 5. Troubleshooting

- **403 from chat endpoints**
  - Ensure user is logged in as `super_admin`, `branch_admin`, or `admin`.
  - Check branch/public IP middleware allows the request.

- **403 from `/broadcasting/auth`**
  - Ensure Echo is sending `Authorization: Bearer <token>`.
  - Confirm token is a valid Sanctum token.

- **Not receiving real‑time messages**
  - Verify Pusher is configured correctly in `.env`.
  - Confirm frontend subscribes to `private('chat.' + chatId)` and listens to `.chat.message.sent`.

This file is the single source of truth for the **1‑on‑1, admin‑only chat** behavior and how to integrate it from the frontend.