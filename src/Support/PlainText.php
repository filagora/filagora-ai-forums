<?php

declare(strict_types=1);

namespace Filagora\AiForums\Support;

/**
 * A post's body as one line of plain words, for embedding and for excerpts.
 */
final class PlainText
{
    public static function of(string $body): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($body)));
    }
}
