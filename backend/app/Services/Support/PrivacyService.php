<?php

namespace App\Services\Support;

use App\Models\Conversation;
use App\Models\User;

/**
 * Enforces the target officer's privacy settings server-side. UI hints are
 * never the control — every exposure of presence, last-seen, etc. passes here.
 */
class PrivacyService
{
    /**
     * Is a privacy-gated attribute visible from $target to $viewer?
     * Settings: everyone | contacts | nobody. "contacts" = they share a
     * conversation (a proxy for an established working relationship).
     */
    public function isVisible(User $target, User $viewer, string $key): bool
    {
        if ($target->id === $viewer->id) {
            return true;
        }

        $setting = ($target->privacy ?? User::defaultPrivacy())[$key] ?? 'everyone';

        return match ($setting) {
            'everyone' => true,
            'nobody' => false,
            'contacts' => $this->shareConversation($target->id, $viewer->id),
            default => true,
        };
    }

    private function shareConversation(string $a, string $b): bool
    {
        return Conversation::whereHas('members', fn ($q) => $q->where('user_id', $a)->whereNull('left_at'))
            ->whereHas('members', fn ($q) => $q->where('user_id', $b)->whereNull('left_at'))
            ->exists();
    }
}
