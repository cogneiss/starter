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
         * No ASCII capital in a stored address, so a write that goes past the
         * model cannot store one. MySQL needs the 'c' flag: its default
         * collation would match a lower-case letter too. SQLite cannot add a
         * constraint to an existing table and goes without. An engine with no
         * form written stops here, before the tables exist without their rule.
         */
        $emailChecks = match (DB::connection()->getDriverName()) {
            'pgsql' => [
                "alter table users add constraint users_email_lowercase check (email !~ '[A-Z]')",
                "alter table password_reset_tokens add constraint password_reset_tokens_email_lowercase check (email !~ '[A-Z]')",
            ],
            'mysql' => [
                "alter table users add constraint users_email_lowercase check (not regexp_like(email, '[A-Z]', 'c'))",
                "alter table password_reset_tokens add constraint password_reset_tokens_email_lowercase check (not regexp_like(email, '[A-Z]', 'c'))",
            ],
            'sqlite' => [],
            default => throw new RuntimeException('No lower-case email check is written for this database driver.'),
        };

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        foreach ($emailChecks as $emailCheck) {
            DB::statement($emailCheck);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
