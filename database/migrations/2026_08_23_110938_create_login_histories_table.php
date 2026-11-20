<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * The same check as on users.email: no ASCII capital in a stored
         * address. SQLite goes without.
         */
        $emailChecks = match (DB::connection()->getDriverName()) {
            'pgsql' => [
                "alter table login_histories add constraint login_histories_email_lowercase check (email !~ '[A-Z]')",
            ],
            'mysql' => [
                "alter table login_histories add constraint login_histories_email_lowercase check (not regexp_like(email, '[A-Z]', 'c'))",
            ],
            'sqlite' => [],
            default => throw new RuntimeException('No lower-case email check is written for this database driver.'),
        };

        Schema::create('login_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('successful');
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
        });

        foreach ($emailChecks as $emailCheck) {
            DB::statement($emailCheck);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
