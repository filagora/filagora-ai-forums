<?php

declare(strict_types=1);

namespace Filagora\AiForums\Tests\Support;

use Filagora\Search\Contracts\WaitsForTasks;
use Filagora\Search\Engines\ArrayEngine;
use Throwable;

/**
 * An engine that applies writes at once, like the array engine, but behaves like one that applies them
 * later: it counts the times it was made to catch up and can be told to fail on the Nth.
 */
class ScriptedEngine extends ArrayEngine implements WaitsForTasks
{
    public int $waits = 0;

    public ?int $failOnWait = null;

    public ?Throwable $failure = null;

    public function waitForTasks(int $timeoutSeconds = 60): void
    {
        $this->waits++;

        if ($this->failOnWait === $this->waits && $this->failure !== null) {
            throw $this->failure;
        }
    }
}
