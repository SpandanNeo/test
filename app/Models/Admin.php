<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;
    protected $primaryKey = "admin_id";
    // protected $guard = 'admin';
    protected $fillable = [
        'name','email', 'password' 
    ];
    //table for admin
    protected $table = 'admin';

    protected $hidden = [
        'password', 'remember_token',
    ];
}
