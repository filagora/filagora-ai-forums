<?php

declare(strict_types=1);

use Filagora\AiForums\Search\ThreadVectorIndex;
use Filagora\AiForums\Tests\Support\TestUser;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;
use Filagora\Search\Support\SearchQuery;

beforeEach(function () {
    $this->stack = aiStack();
    $this->stack['engine']->syncSettings((new ThreadVectorIndex)->definition());
    $this->indexed = fn (): array => array_map('intval', $this->stack['engine']->search((new ThreadVectorIndex)->definition(), new SearchQuery(vector: [0.1, 0.2], perPage: 100))->ids());
    $this->embeddedBatches = fn (): int => $this->stack['embeddings']->batchesEmbedded;
});

it('gives a thread its vector the moment it gains its first reply', function () {
    $thread = aiThread();
    expect(($this->indexed)())->toBe([]);

    aiPost($thread, TestUser::make('Helper'), 'Try X.');
    $thread->forceFill(['reply_count' => 1])->save();

    expect(($this->indexed)())->toBe([$thread->id]);
});

it('does not embed again for each further reply', function () {
    $thread = aiThread(replies: ['Try X.']);
    $before = ($this->embeddedBatches)();

    $thread->forceFill(['reply_count' => 2])->save();
    $thread->forceFill(['reply_count' => 3, 'last_post_at' => now()])->save();

    expect(($this->embeddedBatches)())->toBe($before);
});

it('takes the vector away when the last reply goes', function () {
    $thread = aiThread(replies: ['Try X.']);
    expect(($this->indexed)())->toBe([$thread->id]);

    $thread->forceFill(['reply_count' => 0])->save();

    expect(($this->indexed)())->toBe([]);
});

it('re-embeds a retitled thread', function () {
    $thread = aiThread(replies: ['Try X.']);
    $before = ($this->embeddedBatches)();

    $thread->forceFill(['title' => 'A better title'])->save();

    expect(($this->embeddedBatches)())->toBe($before + 1)
        ->and(end($this->stack['embeddings']->texts))->toStartWith('A better title');
});

it('drops a thread that is no longer approved, or whose forum is moved to an inactive one', function () {
    $thread = aiThread(replies: ['Try X.']);

    $thread->forceFill(['moderation_status' => ContentModerationStatus::PENDING])->save();

    expect(($this->indexed)())->toBe([]);
});

it('re-embeds when the opening post is edited, but not when a later post is', function () {
    $thread = aiThread(opening: 'Original question.', replies: ['Try X.']);
    $before = ($this->embeddedBatches)();
    $posts = Post::query()->where('thread_id', $thread->id)->orderBy('id')->get();

    $posts[1]->forceFill(['body' => 'Try X, edited.'])->save();
    expect(($this->embeddedBatches)())->toBe($before);

    $posts[0]->forceFill(['body' => 'A reworded question.'])->save();
    expect(($this->embeddedBatches)())->toBe($before + 1)
        ->and(end($this->stack['embeddings']->texts))->toContain('A reworded question.');
});

it('re-embeds when the opening post is deleted, so the next post becomes the question', function () {
    $thread = aiThread(opening: 'Original question.', replies: ['The next post.']);
    $before = ($this->embeddedBatches)();

    Post::query()->where('thread_id', $thread->id)->orderBy('id')->first()->forceFill(['is_deleted' => true])->save();

    expect(($this->embeddedBatches)())->toBe($before + 1)
        ->and(end($this->stack['embeddings']->texts))->toContain('The next post.');
});

it('removes the vector when the thread is deleted', function () {
    $thread = aiThread(replies: ['Try X.']);

    Thread::query()->find($thread->id)->delete();

    expect(($this->indexed)())->toBe([]);
});

it('touches nothing while thread vectors are off', function () {
    config(['filagora-ai-forums.thread_vectors.enabled' => false]);
    $before = ($this->embeddedBatches)();

    $thread = aiThread(replies: ['Try X.']);
    $thread->forceFill(['title' => 'Retitled'])->save();

    expect(($this->embeddedBatches)())->toBe($before)
        ->and(($this->indexed)())->toBe([]);
});

it('never lets a failing embedding provider fail the write it is watching', function () {
    $thread = aiThread();
    $this->stack['embeddings']->fails = true;

    aiPost($thread, TestUser::make('Helper'), 'Try X.');
    $thread->forceFill(['reply_count' => 1])->save();

    expect($thread->refresh()->reply_count)->toBe(1)
        ->and(($this->indexed)())->toBe([]);
});
