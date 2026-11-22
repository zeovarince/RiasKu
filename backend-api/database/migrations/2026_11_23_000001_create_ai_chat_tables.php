<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_chat_sessions')) {
            Schema::create('ai_chat_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('session_token')->unique();
                $table->foreignId('pelanggan_id')->nullable()->constrained('pelanggan')->nullOnDelete();
                $table->string('title', 120)->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->index(['pelanggan_id', 'last_message_at']);
            });
        }

        if (! Schema::hasTable('ai_chat_messages')) {
            Schema::create('ai_chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ai_chat_session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();
                $table->string('role', 20);
                $table->text('content');
                $table->json('recommended_products')->nullable();
                $table->json('follow_up_suggestions')->nullable();
                $table->string('provider', 30)->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['ai_chat_session_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
    }
};
