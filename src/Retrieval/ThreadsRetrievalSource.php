<?php

declare(strict_types=1);

namespace Filagora\AiForums\Retrieval;

use Filagora\Ai\Contracts\RetrievalSource;
use Filagora\Ai\Retrieval\RetrievalQuery;
use Filagora\Ai\Services\ChatRetrieval;
use Filagora\AiForums\Search\ThreadVectorIndex;
use Filagora\AiForums\Support\ThreadExcerptBuilder;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Services\ForumAccess;
use Filagora\Forums\Support\Models;
use Filagora\Search\Search;
use Filagora\Search\Support\SearchQuery;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;

/**
 * Earlier forum threads as something the chat can look in: the threads closest in meaning to the
 * message, each shown as the question and the replies most likely to hold the answer.
 *
 * The index holds every answered thread of every active forum, so what the person asking may not
 * open is left out here, per person: a restricted forum's thread must never be paraphrased into an
 * answer for someone who cannot see it. A caller that is itself asked about a thread passes it as the
 * option `exclude_thread_id`, so the thread does not find itself.
 */
final class ThreadsRetrievalSource implements RetrievalSource
{
    /** The index is searched for more than is wanted, since some threads are dropped below. */
    private const OVER_FETCH = 3;

    /**
     * Its collaborators are resolved when it is asked, not when it is registered: it is registered
     * while the application boots, and `ForumAccess` in particular must not be built that early.
     */
    public function __construct(private readonly Container $container) {}

    public function key(): string
    {
        return 'threads';
    }

    public function retrieve(RetrievalQuery $query): array
    {
        if (! ThreadVectorIndex::enabled()) {
            return [];
        }

        $access = $this->container->make(ForumAccess::class);
        $excerpts = $this->container->make(ThreadExcerptBuilder::class);
        $limit = $query->limitFor($this->key(), ChatRetrieval::defaultLimit());
        $ids = array_map('intval', $this->container->make(Search::class)->search(ThreadVectorIndex::NAME, new SearchQuery(vector: $query->vector, perPage: $limit * self::OVER_FETCH))->ids());

        if ($ids === []) {
            return [];
        }

        $threads = Models::class('thread')::query()->whereIn('id', $ids)->with('forum')->get()->keyBy('id');
        $excluded = $query->option('exclude_thread_id');
        $authoredForumIds = $access->authoredForumIds($query->user);
        $items = [];

        foreach ($ids as $id) {
            $thread = $threads->get($id);

            $forum = $thread instanceof Thread ? $thread->forum : null;

            if (! $thread instanceof Thread || ! $forum instanceof Forum || $thread->reply_count < 1) {
                continue;
            }

            if ($excluded !== null && (int) $excluded === (int) $thread->getKey()) {
                continue;
            }

            if (! $access->canViewForum($forum, $query->user, $authoredForumIds) || ! $access->canAccessThread($thread, $query->user)) {
                continue;
            }

            if (($url = $this->link($thread)) === '') {
                continue;
            }

            $items[] = [
                'type' => 'thread',
                'id' => $thread->getKey(),
                'title' => $thread->title,
                'url' => $url,
                'excerpt' => $excerpts->excerpt($thread),
            ];

            if (count($items) === $limit) {
                break;
            }
        }

        return $items;
    }

    private function link(Thread $thread): string
    {
        $route = (string) (config('filagora-ai-forums.assist.thread_route') ?? config('filagora-forums.subscriptions.routes.thread') ?? 'threads.show');

        return Route::has($route) ? (string) route($route, $thread) : '';
    }
}
