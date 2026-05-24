<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hosts', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->comment('Human-friendly label shown in panel');
            $table->string('hostname')->nullable()->comment('Reported by agent at checkin');
            $table->string('api_token', 80)->unique()->comment('Bearer token used by the agent');
            $table->string('status', 16)->default('unknown')->comment('online | offline | unknown');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosts');
    }
};
