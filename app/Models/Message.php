<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    protected $guarded =[];
    protected $table='chat_messages';
   
    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    public function conversation()
	{
        return $this->belongsTo(Conversation::class, 'conversation_id','id');
	}


    public function storeSendReceiveMsg($conversation_id,$type,$user_id)
    {

      return  $this->chats()->create([
            'conversation_id'=>$conversation_id,
            'type'=>$type,
            'user_id'=>$user_id,
        ]);
    }
}
