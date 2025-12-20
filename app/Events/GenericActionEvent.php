<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GenericActionEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // ← CHANGE THIS: Make each field a public property instead of wrapping in $data
    public $resource_type;
    public $action;
    public $resource_id;
    public $user_id;
    public $user_name;
    public $timestamp;

    public function __construct($data)
    {
        $this->resource_type = $data['resource_type'];
        $this->action = $data['action'];
        $this->resource_id = $data['resource_id'];
        $this->user_id = $data['user_id'] ?? null;
        $this->user_name = $data['user_name'] ?? null;
        $this->timestamp = $data['timestamp'] ?? now();
    }

    public function broadcastOn()
    {
        return new Channel('actions');
    }

    public function broadcastAs()
    {
        return 'generic-action';
    }

    public function broadcastWith()
    {
        return [
            'resource_type' => $this->resource_type,
            'action' => $this->action,
            'resource_id' => $this->resource_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'timestamp' => $this->timestamp,
        ];
    }
}
