<?php

declare(strict_types=1);

namespace Filagora\AiForums\Search;

use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Services\EmbeddingBatchFailed;
use Filagora\Ai\Support\Embeddings;
use Filagora\AiForums\Support\PlainText;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;
use Filagora\Forums\Support\Models;
use Filagora\Search\Contracts\BatchesDocuments;
use Filagora\Search\Contracts\SyncedExplicitly;
use Filagora\Search\Support\Document;
use Filagora\Search\Support\IndexDefinition;
use Filagora\Search\Support\SearchQuery;
use Filagora\Search\Support\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * One vector per answered thread, from its title and first post, as a vector-only index of its own.
 * It is kept apart from the keyword index of the same threads: a vector index declares an embedder,
 * and the keyword index's settings should never depend on one.
 *
 * A thread is worth a vector when it is approved, in an active forum, and answered: a question nobody
 * replied to has nothing to retrieve. The index holds only the id and the forum id (to filter by);
 * the excerpt shown to the model is read from the database.
 *
 * Synced by `ThreadVectorSync`, not on every save: each reply saves its thread, and a sync costs an
 * embedding call. A batch whose embedding fails is not sent at all, so a thread never loses the vector
 * it had for one that is empty.
 */
final class ThreadVectorIndex implements BatchesDocuments, SyncedExplicitly
{
    public const NAME = 'thread_vectors';

    public function definition(): IndexDefinition
    {
        return new IndexDefinition(
            name: self::NAME,
            searchable: [],
            filterable: ['forum_id'],
            vectorDimensions: Embeddings::dimensions(),
        );
    }

    public function model(): string
    {
        return Models::class('thread');
    }

    public static function enabled(): bool
    {
        return (bool) config('filagora-ai-forums.thread_vectors.enabled');
    }

    public function shouldBeSearchable(Model $model): bool
    {
        return $model instanceof Thread
            && $model->moderation_status === ContentModerationStatus::APPROVED
            && $model->reply_count >= 1
            && (($model->forum instanceof Forum) ? $model->forum->is_active : true);
    }

    public function toDocument(Model $model): Document
    {
        /** @var Collection<int, Model> $single */
        $single = new Collection([$model]);
        $documents = $this->toDocuments($single);

        // Unembeddable (no text, or the provider failed): the engine needs an explicit "none".
        return $documents[0] ?? new Document($model->getKey(), ['forum_id' => $model instanceof Thread ? $model->forum_id : null]);
    }

    public function toDocuments(Collection $models): array
    {
        $threads = $models->filter(fn (Model $model): bool => $model instanceof Thread)->values();

        if ($threads->isEmpty()) {
            return [];
        }

        $vectors = $this->vectors($threads);

        return $threads->map(fn (Thread $thread, int $position): Document => new Document(
            $thread->getKey(),
            ['forum_id' => (int) $thread->forum_id],
            $vectors[$position] ?? null,
        ))->all();
    }

    public function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Models::class('thread')::query()
            ->where('moderation_status', ContentModerationStatus::APPROVED->value)
            ->where('reply_count', '>=', 1)
            ->whereHas('forum', fn (Builder $forum) => $forum->where('is_active', true))
            ->with('forum:id,is_active');

        return $query;
    }

    public function expectedCount(): int
    {
        return Embeddings::dimensions() === null ? 0 : $this->query()->count();
    }

    public function fallback(SearchQuery $query): SearchResult
    {
        return new SearchResult([], 0);
    }

    /**
     * The text a thread is found by: its title and the start of its first post.
     */
    public static function embeddingText(Thread $thread, ?Post $firstPost): string
    {
        $limit = max(1, (int) config('filagora-ai-forums.thread_vectors.first_post_limit', 1500));

        return trim($thread->title."\n\n".($firstPost !== null ? mb_substr(PlainText::of($firstPost->body), 0, $limit) : ''));
    }

    /**
     * The first approved, undeleted post of each thread, keyed by thread id.
     *
     * @param  list<int>  $threadIds
     * @return array<int, Post>
     */
    public static function firstPostsFor(array $threadIds): array
    {
        if ($threadIds === []) {
            return [];
        }

        $firstPostIds = Models::class('post')::query()
            ->selectRaw('min(id) as id')
            ->whereIn('thread_id', $threadIds)
            ->where('is_deleted', false)
            ->where('moderation_status', ContentModerationStatus::APPROVED->value)
            ->groupBy('thread_id')
            ->pluck('id');

        /** @var array<int, Post> $posts */
        $posts = Models::class('post')::query()
            ->whereIn('id', $firstPostIds)
            ->get(['id', 'thread_id', 'body'])
            ->keyBy('thread_id')
            ->all();

        return $posts;
    }

    /**
     * One embedding call for the whole chunk. A failure is thrown before anything is sent: indexed
     * "without vectors" the chunk would replace the vectors its threads already have.
     *
     * @param  Collection<int, Thread>  $threads
     * @return array<int, list<float>> by position in the chunk
     */
    private function vectors(Collection $threads): array
    {
        if (Embeddings::dimensions() === null) {
            return [];
        }

        $firstPosts = self::firstPostsFor($threads->modelKeys());
        $texts = $threads->map(fn (Thread $thread): string => self::embeddingText($thread, $firstPosts[$thread->getKey()] ?? null))->all();

        try {
            return app(EmbeddingProvider::class)->embedBatch($texts, 'thread_vector_index');
        } catch (Throwable $e) {
            throw new EmbeddingBatchFailed($e->getMessage(), $e);
        }
    }
}
