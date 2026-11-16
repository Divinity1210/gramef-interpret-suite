<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [];

    /**
     * Users participating in this conversation.
     */
    public function participants()
    {
        return $this->belongsToMany(User::class, 'conversation_user');
    }

    /**
     * Messages in this conversation.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The last message in this conversation.
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
