<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTradeDataStatus implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userData;
    // public $accountid = '';
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($userData)
    {
        $this->userData = $userData;
        // $this->accountid = isset($userData['accountid'])?$userData['accountid']:'';
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // return new Channel('MANUAL_TRADE');
        return new Channel(isset($this->userData['accountid'])?$this->userData['accountid'].'_MANUAL_TRADE':'');
    }
}
