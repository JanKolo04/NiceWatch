<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hosts', function (Blueprint $table): void {
            $table->string('api_token_hash', 64)->nullable()->after('api_token');
        });

        // Backfill: hash existing plaintext tokens so existing agents keep working.
        foreach (DB::table('hosts')->select('id', 'api_token')->cursor() as $row) {
            DB::table('hosts')
                ->where('id', $row->id)
                ->update(['api_token_hash' => hash('sha256', (string) $row->api_token)]);
        }

        Schema::table('hosts', function (Blueprint $table): void {
            $table->string('api_token_hash', 64)->nullable(false)->change();
            $table->unique('api_token_hash');
            $table->dropUnique(['api_token']);
            $table->dropColumn('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table): void {
            $table->string('api_token', 80)->nullable()->after('hostname');
        });
        // No way to recover plaintext from hash — leave api_token empty on rollback.
        Schema::table('hosts', function (Blueprint $table): void {
            $table->unique('api_token');
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn('api_token_hash');
        });
    }
};
