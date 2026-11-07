<?php

declare(strict_types=1);

namespace Filagora\AiForums\Tests\Support;

use Filagora\Ai\Contracts\RetrievalSource;
use Filagora\Ai\Retrieval\RetrievalQuery;
use RuntimeException;

class StaticSource implements RetrievalSource
{
    public ?RetrievalQuery $lastQuery = null;

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(private readonly string $key, private readonly array $items = [], private readonly bool $fails = false) {}

    public function key(): string
    {
        return $this->key;
    }

    public function retrieve(RetrievalQuery $query): array
    {
        $this->lastQuery = $query;

        if ($this->fails) {
            throw new RuntimeException('index unreachable');
        }

        return $this->items;
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(string $title, array $extra = []): array
    {
        return ['type' => 'doc', 'id' => 1, 'title' => $title, 'url' => 'https://example.test/'.$title, 'excerpt' => "About {$title}."] + $extra;
    }
}
