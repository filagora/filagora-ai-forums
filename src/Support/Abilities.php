<?php

declare(strict_types=1);

namespace Filagora\AiForums\Support;

/**
 * The Gate ability that guards thread assist. It maps to an entitlement key in
 * `filagora-ai-forums.gates`, which a host can point at its own key.
 */
final class Abilities
{
    public const THREAD_ASSIST = 'filagora.ai.thread-assist';
}
