<?php

declare(strict_types=1);

namespace Filagora\AiForums\Tests\Support;

use Closure;
use Filagora\Ai\Contracts\EmbeddingProvider;
use RuntimeException;

class FakeEmbeddingProvider implements EmbeddingProvider
{
    public bool $fails = false;

    /** @var array<string, list<float>> vectors by the first word of the text */
    public array $vectors = [];

    /** @var list<string> every text embedded, batch by batch flattened */
    public array $texts = [];

    /** The length of the vectors it returns, when that should differ from the length it declares. */
    public ?int $dimensionsReturned = null;

    /** Fail every batch after this many have been embedded. */
    public ?int $failAfterBatches = null;

    /** How many batches (not counting a preflight) were embedded. */
    public int $batchesEmbedded = 0;

    /** @var (Closure(string): void)|null called with the context of each batch */
    public ?Closure $recordUsage = null;

    public function embed(string $text, ?string $context = null): array
    {
        if ($this->fails) {
            throw new RuntimeException('embeddings are down');
        }

        return $this->vectors[strtok($text, "\n ")] ?? [0.1, 0.2];
    }

    public function embedBatch(array $texts, ?string $context = null): array
    {
        if ($this->fails || ($this->failAfterBatches !== null && $this->batchesEmbedded >= $this->failAfterBatches)) {
            throw new RuntimeException('embeddings are down');
        }

        if (! str_ends_with((string) $context, '_preflight')) {
            $this->batchesEmbedded++;

            if ($this->recordUsage !== null) {
                ($this->recordUsage)((string) $context);
            }
        }

        $length = $this->dimensionsReturned ?? 2;

        $this->texts = [...$this->texts, ...$texts];

        return array_map(fn (string $text): array => $this->vectors[strtok($text, "\n ")] ?? array_fill(0, $length, 0.1), $texts);
    }

    public function dimensions(): int
    {
        return 2;
    }
}
