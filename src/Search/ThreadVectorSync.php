<?php

declare(strict_types=1);

namespace Filagora\AiForums\Search;

use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;
use Filagora\Search\Indexing\Indexer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the thread vectors in step with the threads and their opening posts.
 *
 * Every reply saves its thread (reply count, last post time), and a sync costs an embedding call, so
 * this acts only on changes that alter whether a thread has a vector or what it says: a retitled
 * thread, a moderation or forum change, the moment it gains its first reply or loses its last, and a
 * change to the opening post (edited, deleted, restored, approved, unapproved or moved, any of which
 * can change which post is the opener). It never lets a failing search engine or embedding provider
 * fail the write it is watching.
 */
final class ThreadVectorSync
{
    private const TRACKED_POST_FIELDS = ['body', 'is_deleted', 'moderation_status', 'thread_id'];

    public function __construct(private readonly Indexer $indexer) {}

    /**
     * A new thread has no replies and so no vector yet; only one created already answered (an import,
     * say) needs one straight away.
     */
    public function threadCreated(Thread $thread): void
    {
        if (ThreadVectorIndex::enabled() && $thread->reply_count >= 1) {
            $this->syncThread((int) $thread->getKey());
        }
    }

    public function threadSaved(Thread $thread): void
    {
        if (ThreadVectorIndex::enabled() && $this->affectsVector($thread)) {
            $this->syncThread((int) $thread->getKey());
        }
    }

    public function threadDeleted(Thread $thread): void
    {
        if (! ThreadVectorIndex::enabled()) {
            return;
        }

        try {
            $this->indexer->forget(new ThreadVectorIndex, $thread->getKey());
        } catch (Throwable $e) {
            $this->warn((int) $thread->getKey(), $e);
        }
    }

    public function postSaved(Post $post): void
    {
        if (! $post->wasChanged(self::TRACKED_POST_FIELDS)) {
            return;
        }

        $threadIds = [(int) $post->thread_id];

        if ($post->wasChanged('thread_id')) {
            $threadIds[] = (int) ($post->getPrevious()['thread_id'] ?? 0);
        }

        foreach (array_unique(array_filter($threadIds)) as $threadId) {
            $this->syncIfOpener($post, $threadId);
        }
    }

    public function postDeleted(Post $post): void
    {
        $this->syncIfOpener($post, (int) $post->thread_id);
    }

    public function syncThread(int $threadId): void
    {
        if (! ThreadVectorIndex::enabled()) {
            return;
        }

        try {
            $thread = Models::class('thread')::query()->with('forum:id,is_active')->find($threadId);

            if ($thread !== null) {
                $this->indexer->sync(new ThreadVectorIndex, $thread);
            }
        } catch (Throwable $e) {
            $this->warn($threadId, $e);
        }
    }

    private function affectsVector(Thread $thread): bool
    {
        if ($thread->wasChanged(['title', 'moderation_status', 'forum_id'])) {
            return true;
        }

        // Crossing the answered line in either direction: the first reply adds the vector, losing the
        // last one (moderation, deletion, relocation) removes it.
        return $thread->wasChanged('reply_count')
            && ((int) ($thread->getPrevious()['reply_count'] ?? 0) >= 1) !== ($thread->reply_count >= 1);
    }

    /**
     * Only the opener matters: a change to any later post leaves the vector as it is, and an
     * unanswered thread has no vector to refresh.
     */
    private function syncIfOpener(Post $post, int $threadId): void
    {
        if (! ThreadVectorIndex::enabled()) {
            return;
        }

        $answered = Models::class('thread')::query()->whereKey($threadId)->where('reply_count', '>=', 1)->exists();

        if (! $answered) {
            return;
        }

        $openerId = Models::class('post')::query()
            ->where('thread_id', $threadId)
            ->where('is_deleted', false)
            ->where('moderation_status', ContentModerationStatus::APPROVED->value)
            ->min('id');

        // A post after the current opener cannot have been the opener, or become it. One at or before
        // it is the opener now, or was until this change removed it.
        if ($openerId !== null && (int) $post->getKey() > (int) $openerId) {
            return;
        }

        $this->syncThread($threadId);
    }

    private function warn(int $threadId, Throwable $e): void
    {
        Log::warning('Failed to sync a thread vector.', ['thread_id' => $threadId, 'exception' => $e->getMessage()]);
    }
}
