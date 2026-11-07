<?php

declare(strict_types=1);

use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\AiForums\Tests\Support\FakeChatProvider;
use Filagora\AiForums\Tests\Support\FakeEmbeddingProvider;
use Filagora\AiForums\Tests\Support\ScriptedEngine;
use Filagora\AiForums\Tests\Support\TestUser;
use Filagora\AiForums\Tests\TestCase;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Enums\ForumPostingPolicy;
use Filagora\Forums\Enums\ForumVisibility;
use Filagora\Forums\Models\Forum;
use Filagora\Forums\Models\ForumGroup;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;
use Filagora\Search\Support\SearchEngines;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Embeddings and chat on, an engine that behaves like one that applies writes later, and fakes for
 * the two providers; the thread vectors live.
 *
 * @return array{engine: ScriptedEngine, embeddings: FakeEmbeddingProvider, chat: FakeChatProvider}
 */
function aiStack(): array
{
    config([
        'filagora-search.engine' => 'scripted',
        'filagora-ai.embeddings.enabled' => true,
        'filagora-ai.chat.enabled' => true,
        'filagora-ai-forums.thread_vectors.enabled' => true,
    ]);

    $engine = new ScriptedEngine;
    app(SearchEngines::class)->extend('scripted', fn () => $engine);
    $embeddings = new FakeEmbeddingProvider;
    app()->instance(EmbeddingProvider::class, $embeddings);
    $chat = new FakeChatProvider;
    app()->instance(ChatProvider::class, $chat);

    return ['engine' => $engine, 'embeddings' => $embeddings, 'chat' => $chat];
}

function aiForum(ForumVisibility $visibility = ForumVisibility::PUBLIC, bool $active = true): Forum
{
    $group = ForumGroup::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main']);

    return Forum::create([
        'forum_group_id' => $group->id,
        'name' => 'Forum '.uniqid(),
        'slug' => 'forum-'.uniqid(),
        'visibility' => $visibility,
        'posting_policy' => ForumPostingPolicy::REGISTERED,
        'is_active' => $active,
    ]);
}

function aiPost(Thread $thread, TestUser $author, string $body, array $attributes = []): Post
{
    return Post::create($attributes + ['thread_id' => $thread->id, 'author_id' => $author->id, 'body' => $body]);
}

/**
 * A thread with an opening post and, when asked, replies (each by a different person) that count
 * towards `reply_count`.
 *
 * @param  list<string>  $replies
 */
function aiThread(?Forum $forum = null, string $title = 'Blank page after checkout', string $opening = 'My checkout shows a blank page.', array $replies = []): Thread
{
    $forum ??= aiForum();
    $asker = TestUser::make('Asker'.uniqid());
    $thread = Thread::create(['forum_id' => $forum->id, 'author_id' => $asker->id, 'title' => $title, 'slug' => 't-'.uniqid()]);
    aiPost($thread, $asker, $opening);

    foreach ($replies as $reply) {
        aiPost($thread, TestUser::make('Helper'.uniqid()), $reply);
    }

    $thread->forceFill(['reply_count' => count($replies), 'moderation_status' => ContentModerationStatus::APPROVED])->save();

    return $thread->refresh();
}
