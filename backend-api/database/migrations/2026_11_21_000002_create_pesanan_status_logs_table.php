<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pesanan_status_logs')) {
            Schema::create('pesanan_status_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->string('actor_type', 20)->default('system');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name', 120)->nullable();
                $table->string('tracking_number', 100)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['pesanan_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pesanan_status_logs');
    }
};
