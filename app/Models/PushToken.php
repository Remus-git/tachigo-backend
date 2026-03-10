<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushToken extends Model
{
    protected $fillable = ['user_id', 'token', 'last_used_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
