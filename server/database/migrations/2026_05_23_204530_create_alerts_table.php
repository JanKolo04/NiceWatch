<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64)->comment('disk_high | host_offline | ...');
            $table->string('key', 191)->comment('Stable identifier for throttling, e.g. disk_high:/var');
            $table->string('severity', 16)->default('warning');
            $table->string('message');
            $table->json('payload')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['host_id', 'type', 'key', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
