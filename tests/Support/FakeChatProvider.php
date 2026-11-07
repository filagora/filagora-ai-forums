<?php

declare(strict_types=1);

namespace Filagora\AiForums\Tests\Support;

use Filagora\Ai\Contracts\ChatProvider;
use Generator;

class FakeChatProvider implements ChatProvider
{
    /** @var list<array{messages: list<array{role: string, content: string}>, context: ?string, userId: ?int}> */
    public array $calls = [];

    /**
     * @param  list<string>  $deltas
     */
    public function __construct(public array $deltas = ['Hello ', 'there.']) {}

    public function providerName(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-1';
    }

    public function streamReply(array $messages, ?string $context = null, ?int $userId = null): Generator
    {
        $this->calls[] = ['messages' => $messages, 'context' => $context, 'userId' => $userId];

        foreach ($this->deltas as $delta) {
            yield $delta;
        }

        return ['input_tokens' => 10, 'output_tokens' => 5];
    }
}
