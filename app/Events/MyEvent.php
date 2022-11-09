<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MyEvent implements ShouldBroadcast
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $message;
  private $userId;

  public function __construct($message, $userId)
  {
      $this->message = $message;
      $this->userId = $userId;
  }

  public function broadcastOn()
  {
      return new Channel('my-channel-' . $this->userId);
  }

  public function broadcastAs()
  {
      return 'my-event-' . $this->userId;
  }
  
}