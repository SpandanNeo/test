<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Sound extends Model
{
    public $table = 'sounds';
    protected $fillable = [
        'sound_id',
        'title',
        'cat_id',
        'sound_name',
        'album',
        'artist'
    ];
}
