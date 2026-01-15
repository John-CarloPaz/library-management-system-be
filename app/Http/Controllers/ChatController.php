<?php

namespace App\Http\Controllers;

use App\Events\NotificationCreated;
use App\Events\ChatMessageSent;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function __construct(private PermissionService $permissions)
    {
    }

    /**
     * List chats that the authenticated admin participates in.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$this->permissions->canUseChat($user)) {
            return response()->json(['message' => 'Access denied: Chat is for admins only.'], 403);
        }

        $chats = $user->chats()
            ->with(['users:id,username,first_name,last_name', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->orderByDesc('chats.updated_at')
            ->get();

        return response()->json([
            'chats' => $chats,
        ]);
    }

    /**
     * Create (or reuse) a 1-on-1 chat between admins.
     * Strictly two participants: the authenticated admin and a single recipient.
     *
     * Request body:
     *   {
     *     "recipient_id": number // admin user ID to chat with
     *   }
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$this->permissions->canUseChat($user)) {
            return response()->json(['message' => 'Access denied: Chat is for admins only.'], 403);
        }

        $validated = $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
        ]);

        // Prevent self-chat
        if ((int) $validated['recipient_id'] === (int) $user->id) {
            return response()->json(['message' => 'You cannot start a chat with yourself.'], 422);
        }

        // Ensure recipient is an admin-role user
        $recipient = User::where('id', $validated['recipient_id'])
            ->whereIn('role', ['super_admin', 'branch_admin', 'admin'])
            ->first();

        if (!$recipient) {
            return response()->json(['message' => 'Recipient must be an admin user.'], 422);
        }

        $currentId = (int) $user->id;
        $recipientId = (int) $recipient->id;

        // Try to reuse an existing 1-on-1 chat between these two admins
        $chat = Chat::whereHas('users', function ($q) use ($currentId) {
                $q->where('users.id', $currentId);
            })
            ->whereHas('users', function ($q) use ($recipientId) {
                $q->where('users.id', $recipientId);
            })
            ->first();

        if (!$chat) {
            $chat = Chat::create([
                'name' => null,
                'is_group' => false,
                'created_by' => $currentId,
            ]);

            $chat->users()->sync([$currentId, $recipientId]);
        }

        $chat->load('users:id,username,first_name,last_name');

        return response()->json([
            'message' => '1-on-1 chat ready.',
            'chat' => $chat,
        ], 201);
    }

    /**
     * Get paginated messages for a chat the admin participates in.
     */
    public function messages(Request $request, $chatId)
    {
        $user = Auth::user();

        if (!$this->permissions->canUseChat($user)) {
            return response()->json(['message' => 'Access denied: Chat is for admins only.'], 403);
        }

        $chat = Chat::with('users:id,username,first_name,last_name')->findOrFail($chatId);

        if (!$chat->users->contains('id', $user->id)) {
            return response()->json(['message' => 'Access denied: You are not a participant in this chat.'], 403);
        }

        $perPage = (int) $request->get('per_page', 25);

        $messages = ChatMessage::where('chat_id', $chat->id)
            ->with('user:id,username,first_name,last_name')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'chat' => $chat,
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message in a chat; broadcasts via Pusher.
     * Request: { message: string }
     */
    public function sendMessage(Request $request, $chatId)
    {
        $user = Auth::user();

        if (!$this->permissions->canUseChat($user)) {
            return response()->json(['message' => 'Access denied: Chat is for admins only.'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $chat = Chat::with('users:id')->findOrFail($chatId);

        if (!$chat->users->contains('id', $user->id)) {
            return response()->json(['message' => 'Access denied: You are not a participant in this chat.'], 403);
        }

        $message = ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
        ]);

        $chat->touch();

        ChatMessageSent::dispatch($message);

        // Create notifications for all other participants in the chat
        $recipientIds = $chat->users
            ->where('id', '!=', $user->id)
            ->pluck('id');

        foreach ($recipientIds as $recipientId) {
            $notification = Notification::create([
                'user_id' => $recipientId,
                'type' => 'chat',
                'title' => 'New chat message',
                'message' => 'New message from ' . $user->username,
                'data' => [
                    'chat_id' => $chat->id,
                    'message_id' => $message->id,
                    'sender_id' => $user->id,
                    'sender_username' => $user->username,
                ],
            ]);

            NotificationCreated::dispatch($notification);
        }

        return response()->json([
            'message' => 'Message sent successfully.',
            'chat_message' => $message->load('user:id,username,first_name,last_name'),
        ], 201);
    }
}
