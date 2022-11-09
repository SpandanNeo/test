<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
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
            'id'=>$this->id,
            'open'=>false,
            'users'=>[$this->user_from,$this->user_to],

            'unReadCount'=>$this->chats->where('read_at','==',null)->where('type',0)->where('user_id','!=',Auth::id())->count()
        ];
    }
}
