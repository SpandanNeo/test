<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ApiConversationResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiUserResource extends JsonResource
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
        $conversation=Conversation::whereIn('user_from',[Auth::guard('api')->id(),$id])->whereIn('user_to',[Auth::guard('api')->id(),$id])->first();
        
        return new ApiConversationResource($conversation) ;


    }
}
