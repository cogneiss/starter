<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A view of a list, kept by the person who arranged it.
     *
     * Both owners are columns rather than conventions: a saved search belongs to
     * one user inside one organization, so every read and every write can be a
     * where clause instead of a check after the fact. Nothing here is shared,
     * which is why there is no visibility column to get wrong later.
     *
     * The stored query is json rather than a set of columns because it is the
     * serialized `ResourceQuery` and that shape will keep moving. It is
     * re-validated on the way out, so a saved sort on a column that no longer
     * exists degrades to the default order rather than breaking the screen.
     */
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('resource');
            $table->string('name');
            $table->json('query');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            // The predicate every read and write runs on, in the order they run
            // it: one person's saved views of one list.
            $table->index(['organization_id', 'user_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
