<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The retrieval corpus. One row is one chunk of one record, embedded once.
     *
     * The embedding column is a real pgvector column on Postgres and plain text
     * everywhere else: a sqlite checkout still migrates, still runs every test
     * that is not about vector search, and simply has no retrieval.
     */
    public function up(): void
    {
        $vectors = DB::connection()->getDriverName() === 'pgsql';

        if ($vectors) {
            Schema::ensureVectorExtensionExists();
        }

        Schema::create('ai_documents', function (Blueprint $table) use ($vectors): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->string('source_id');
            $table->string('title');
            $table->text('content');

            $vectors
                ? $table->vector('embedding', dimensions: 1536)->index()
                : $table->text('embedding')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_documents');
    }
};
