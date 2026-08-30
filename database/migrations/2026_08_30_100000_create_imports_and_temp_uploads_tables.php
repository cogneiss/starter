<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An upload arrives before anybody knows whether it is safe, so it lands in
     * a holding table with its own expiry and its own scan verdict. Only a
     * clean, promoted upload is ever read by anything else, and the nightly
     * prune removes the rest along with their bytes.
     *
     * A batch is the record of one import run, and a row is one line of that
     * file. The row keeps its own status and its own messages: a bad line is a
     * result, not an interruption, so the ninety-seven good lines still land and
     * the three bad ones can be corrected and re-run on their own.
     */
    public function up(): void
    {
        Schema::create('temp_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamp('scanned_at')->nullable();
            $table->string('scan_result')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('temp_upload_id')->nullable()->constrained()->nullOnDelete();
            $table->string('import');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->json('data');
            $table->string('status')->default('pending');
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('temp_uploads');
    }
};
