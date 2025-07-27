<?php


namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserClickedButton implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public $payload;
    protected $mentorId;

    public function __construct(int $mentorId, int $menteeId, string $menteeName)
    {
        $this->mentorId = $mentorId;
        $this->payload = [
            'title' => 'Test Notification',
            'message' => "Mentee {$menteeName} clicked the test button",
            'mentee_id' => $menteeId,
        ];
    }

    public function broadcastOn()
    {
        // matches your existing private channel for that user
        return new PrivateChannel("App.Models.User.{$this->mentorId}");
    }

    public function broadcastAs()
    {
        return 'user.clicked';
    }
}
