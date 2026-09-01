<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class DeleteAccount
{
    public function __construct(private AssertNotLastActiveOwner $guard) {}

    /**
     * The GDPR deletion: wipe what can be wiped, anonymise what other tenants'
     * records still reference, then soft delete the row so gdpr:purge can hard
     * delete it after the retention window. The original name, email, IP and
     * user agent survive nowhere — a schema-wide sweep in GdprDeleteTest holds
     * this to account.
     */
    public function handle(User $user): void
    {
        $user->memberships()
            ->where('status', MembershipStatus::Active)
            ->get()
            ->each(fn ($membership) => $this->guard->handle($membership));

        $email = $user->email;

        DB::transaction(function () use ($user, $email): void {
            foreach (['ai_memories', 'agent_conversations', 'ai_confirm_tokens', 'saved_searches', 'passkeys', 'social_accounts'] as $table) {
                DB::table($table)->where('user_id', $user->id)->delete();
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();

            DB::table('personal_access_tokens')
                ->where('created_by', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            DB::table('notifications')->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->delete();

            DB::table('temp_uploads')->where('user_id', $user->id)->get()
                ->each(function (object $upload): void {
                    if (is_string($upload->disk) && is_string($upload->path)) {
                        Storage::disk($upload->disk)->delete($upload->path);
                    }
                });
            DB::table('temp_uploads')->where('user_id', $user->id)->delete();

            // Login history keeps its rows — the security trail matters — but
            // the person inside them is erased. Rows matched by id or by the
            // original email, because failed attempts have no user_id.
            DB::table('login_histories')
                ->where('user_id', $user->id)
                ->orWhere('email', $email)
                ->update(['email' => 'anonymised@example.invalid', 'ip_address' => null, 'user_agent' => null]);

            DB::table('impersonation_logs')
                ->where('impersonator_user_id', $user->id)
                ->orWhere('impersonated_user_id', $user->id)
                ->update(['ip_address' => null, 'user_agent' => null]);

            DB::table('organization_invitations')->where('email', $email)->delete();

            // Existing audit entries keep their facts but lose their author,
            // before the anonymisation entries below are written with no causer.
            DB::table('activity_log')
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->id)
                ->update(['causer_type' => null, 'causer_id' => null]);

            $user->memberships()->pluck('organization_id')->unique()
                ->each(fn (mixed $organizationId) => Activity::query()->create([
                    'organization_id' => $organizationId,
                    'log_name' => 'audit',
                    'description' => 'A member account was anonymised and scheduled for purge.',
                    'event' => 'anonymised',
                    'properties' => [],
                ]));

            $user->forceFill([
                'name' => 'Anonymised user',
                'email' => 'anonymised-'.$user->id.'@example.invalid',
                'password' => Hash::make(Str::random(40)),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'notification_preferences' => null,
                'is_active' => false,
            ])->save();

            $user->delete();
        });
    }
}
