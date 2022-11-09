<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'=> $this->id,
            'name'=> $this->name,
            'email'=> $this->email,
            'online'=> false,
            'conversation'=> $this->conversationCheck($this->id),
        ];
    }

    private function conversationCheck($id)
    {
        $conversation=Conversation::whereIn('user_from',[Auth::id(),$id])->whereIn('user_to',[Auth::id(),$id])->first();
        
        return new ConversationResource($conversation) ;


    }
}
