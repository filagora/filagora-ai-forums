<?php

declare(strict_types=1);

use Filagora\AiForums\Support\ThreadExcerptBuilder;
use Filagora\AiForums\Tests\Support\TestUser;
use Filagora\Forums\Enums\ContentModerationStatus;
use Filagora\Forums\Models\Post;
use Filagora\Forums\Models\Thread;

function excerptOf(Thread $thread): string
{
    return app(ThreadExcerptBuilder::class)->excerpt($thread);
}

it('shows the question and nothing else for a thread nobody answered', function () {
    $thread = aiThread(opening: 'Where is the setting?');

    expect(excerptOf($thread))->toBe('Question: Where is the setting?');
});

it('shows the first reply, the two longest others and the last, not the rest', function () {
    $thread = aiThread(opening: 'It breaks.', replies: [
        'first reply',
        'short one',
        str_repeat('a long reply ', 12),
        'another short',
        str_repeat('the longest reply ', 20),
        'last reply',
    ]);

    $excerpt = excerptOf($thread);

    expect($excerpt)->toStartWith('Question: It breaks.')
        ->and($excerpt)->toContain('Reply: first reply')
        ->and($excerpt)->toContain('Reply: last reply')
        ->and($excerpt)->toContain('the longest reply')
        ->and($excerpt)->toContain('a long reply')
        ->and($excerpt)->not->toContain('short one')
        ->and($excerpt)->not->toContain('another short');
});

it('leaves out what the asker said themselves when picking replies', function () {
    $thread = aiThread(opening: 'It breaks.', replies: ['A helper answers.']);
    aiPost($thread, TestUser::query()->find($thread->author_id), 'The asker adds detail, at length. '.str_repeat('more ', 40));

    $excerpt = excerptOf($thread);

    expect($excerpt)->toContain('A helper answers.')
        ->and($excerpt)->not->toContain('The asker adds detail');
});

it('never quotes a deleted or unapproved post', function () {
    $thread = aiThread(opening: 'It breaks.', replies: ['Good answer.', 'Deleted answer.', 'Unapproved answer.']);
    $posts = Post::query()->where('thread_id', $thread->id)->orderBy('id')->get();
    $posts[2]->forceFill(['is_deleted' => true])->save();
    $posts[3]->forceFill(['moderation_status' => ContentModerationStatus::PENDING])->save();

    $excerpt = excerptOf($thread);

    expect($excerpt)->toContain('Good answer.')
        ->and($excerpt)->not->toContain('Deleted answer.')
        ->and($excerpt)->not->toContain('Unapproved answer.');
});

it('strips markup and clips long posts', function () {
    $thread = aiThread(opening: '<p>'.str_repeat('word ', 200).'</p>', replies: ['<b>Bold</b> answer']);

    $excerpt = excerptOf($thread);

    expect($excerpt)->toContain('Reply: Bold answer')
        ->and(mb_strlen(explode("\n\n", $excerpt)[0]))->toBeLessThanOrEqual(ThreadExcerptBuilder::QUESTION_LENGTH + 12);
});

it('gives nothing for a thread with no live posts', function () {
    $thread = aiThread(opening: 'Gone');
    Post::query()->where('thread_id', $thread->id)->update(['is_deleted' => true]);

    expect(excerptOf($thread))->toBe('');
});
