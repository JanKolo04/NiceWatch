<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->json('payload')->comment('Full agent checkin payload');
            $table->timestamp('collected_at')->comment('Time reported by the agent');
            $table->timestamps();

            $table->index(['host_id', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }
};
