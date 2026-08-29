<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The in-app inbox. Laravel's own notifications table with one addition:
     * the organization the notification was raised in.
     *
     * It is a column rather than a key inside the json payload so that every
     * read is a where clause. A person who belongs to two organizations must
     * not see the first one's inbox while acting as the second, and the only
     * way to promise that is to make the tenant part of the query.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The predicate the bell and the panel run, in the order they run
            // it: one person's unread notifications in one organization.
            $table->index(['notifiable_type', 'notifiable_id', 'organization_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
