<?php

declare(strict_types=1);

namespace Filagora\AiForums\Assist;

/**
 * What one thread-assist request will do, decided before anything is spent: either a stored answer to
 * replay, or the prompt to send.
 */
final readonly class ThreadAssistPlan
{
    /**
     * @param  list<array{role: 'system'|'user'|'assistant', content: string}>  $messages  empty when there is a stored answer
     * @param  list<array{type: string, title: string, url: string}>  $sources
     * @param  list<string>  $notes  what the panel says about the material found (such as an assumed version)
     */
    public function __construct(
        public string $cacheKey,
        public array $messages,
        public array $sources,
        public array $notes,
        public ?string $storedAnswer,
    ) {}

    public function isStored(): bool
    {
        return $this->storedAnswer !== null;
    }
}
