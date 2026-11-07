<?php

declare(strict_types=1);

namespace Filagora\AiForums\Assist;

use Filagora\AiForums\Support\Abilities;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may ask the AI to help with a thread. Every condition lives here so the button, the route and
 * any later widening of access agree: the chat is switched on, the person holds the thread-assist
 * entitlement, and they could open the thread themselves. Without the last condition the answer
 * could paraphrase a thread from a forum they cannot see.
 */
class ThreadAssistGate
{
    public function __construct(private readonly Gate $gate, private readonly ForumAccess $access) {}

    public function allows(?Authenticatable $user, Thread $thread): bool
    {
        if ($user === null || ! (bool) config('filagora-ai.chat.enabled')) {
            return false;
        }

        if (! $this->gate->forUser($user)->allows(Abilities::THREAD_ASSIST)) {
            return false;
        }

        $thread->loadMissing('forum');
        $forum = $thread->forum;

        return $forum instanceof Forum
            && $this->access->canViewForum($forum, $user)
            && $this->access->canAccessThread($thread, $user);
    }
}
