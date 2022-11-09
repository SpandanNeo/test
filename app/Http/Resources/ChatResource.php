<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
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
            'message' => $this->message['msg'],
            'id'=>$this->id,
            'type'=>$this->type,
           'read_at'=> $this->read_at($this),
            'send_at'=>$this->created_at->diffForHumans(),
        ];
    }

    private function read_at($time)
    {
        $reat_at= $time->type==0 ? $this->read_at:"";

        return $reat_at ? $reat_at->diffForHumans():null;

    }
}
