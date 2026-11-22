<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    protected $table = 'ai_chat_messages';

    public const UPDATED_AT = null;

    protected $fillable = [
        'ai_chat_session_id',
        'role',
        'content',
        'recommended_products',
        'follow_up_suggestions',
        'provider',
        'latency_ms',
        'created_at',
    ];

    protected $casts = [
        'recommended_products' => 'array',
        'follow_up_suggestions' => 'array',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'ai_chat_session_id');
    }
}
