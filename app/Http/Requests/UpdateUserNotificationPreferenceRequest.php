<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserNotificationPreferenceRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (User::NOTIFICATION_CHANNELS as $notification => $channels) {
            foreach ($channels as $channel) {
                $rules[$notification.'.'.$channel] = ['required', 'boolean'];
            }
        }

        return $rules;
    }

    /**
     * The submitted matrix, as the user column stores it.
     *
     * @return array<string, array<string, bool>>
     */
    public function preferences(): array
    {
        $preferences = [];

        foreach (User::NOTIFICATION_CHANNELS as $notification => $channels) {
            foreach ($channels as $channel) {
                $preferences[$notification][$channel] = $this->boolean($notification.'.'.$channel);
            }
        }

        return $preferences;
    }
}
