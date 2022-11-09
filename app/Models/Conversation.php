<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $guarded = [];
    protected $table='chat_conversations';
    protected $fillable = [
        'user_from','user_to' 
    ];

    public function chats()
    {
        return $this->hasManyThrough(
            Chat::class,
            Message::class,
            // Post::class,
            'conversation_id',
            'message_id'
            // 'product_id'

        )->latest();
    }
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function messages_list()
    {
        return $this->hasMany(Message::class)->latest();
    }

	public function post()
	{
        return $this->belongsTo(Post::class, 'product_id','id');
	}


    public function deleteChats($user)
    {
        return $this->chats()->where('user_id', $user)->delete();
    }
    public function deleteMessages()
    {
        return $this->messages()->delete();
    }
}
