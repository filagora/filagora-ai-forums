<?php

declare(strict_types=1);

namespace Filagora\AiForums\Console;

use Filagora\Ai\Indexing\BackfillStop;
use Filagora\Ai\Indexing\VectorBackfill;
use Filagora\AiForums\Search\ThreadVectorIndex;
use Illuminate\Console\Command;

class ThreadVectorsBackfillCommand extends Command
{
    private const NUMERIC_OPTIONS = ['limit', 'from-id', 'batch-size', 'sleep-ms', 'catch-up-every'];

    /** More digits than this cannot be a sensible number of threads, ids or milliseconds. */
    private const MAX_OPTION_DIGITS = 9;

    /**
     * @var string
     */
    protected $signature = 'thread-vectors:backfill
        {--limit= : Stop after this many threads (a cheap trial before the full run)}
        {--from-id= : Start at this thread id, to resume an interrupted run}
        {--batch-size=100 : Threads embedded per request}
        {--sleep-ms=250 : Pause between batches, to keep the load off the server}
        {--catch-up-every=2000 : After this many threads, wait for the search engine to catch up before sending more}
        {--dry-run : Count what would be embedded and push nothing}';

    /**
     * @var string
     */
    protected $description = 'Embed answered threads into the thread_vectors index. Repeating it is safe (it overwrites); do not run two at once.';

    /**
     * @var string
     */
    protected $help = <<<'HELP'
Stop it with Ctrl-C whenever you like and continue with --from-id=<last id shown + 1>.

Before sending anything it checks that the search engine answers and that the embedding provider answers and returns vectors of the size the index expects. If the provider fails on a batch mid-run, nothing from that batch is sent (so no thread is left without a vector, and none loses one it had) and the run stops; fix the provider and continue from the id it prints.

It also stops, and says so, if the search engine rejects what it is sent or cannot keep up, and it exits non-zero unless it could confirm that everything was indexed.
HELP;

    public function handle(VectorBackfill $backfill): int
    {
        foreach (self::NUMERIC_OPTIONS as $name) {
            $value = $this->option($name);

            if ($value !== null && $value !== '' && (! ctype_digit((string) $value) || strlen((string) $value) > self::MAX_OPTION_DIGITS)) {
                $this->error("--{$name} must be a whole number.");

                return self::FAILURE;
            }
        }

        $limit = $this->intOption('limit');
        $fromId = $this->intOption('from-id');
        $batchSize = max(1, (int) $this->option('batch-size'));
        $index = new ThreadVectorIndex;

        $query = $index->query();

        if ($fromId !== null) {
            $query->where($query->getModel()->getQualifiedKeyName(), '>=', $fromId);
        }

        $total = $query->count();
        $planned = $limit !== null ? min($total, $limit) : $total;

        $this->line(sprintf(
            '%s answered threads%s%s; embedding %s in batches of %s.',
            number_format($total),
            $fromId !== null ? " from id {$fromId}" : '',
            $limit !== null ? ", limit {$limit}" : '',
            number_format($planned),
            number_format($batchSize),
        ));

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was embedded or pushed.');

            return self::SUCCESS;
        }

        $result = $backfill->run(
            $index,
            'thread_vector_index',
            $fromId,
            $batchSize,
            $limit,
            (int) $this->option('sleep-ms'),
            max(1, (int) $this->option('catch-up-every')),
            function (int $sent, int|string|null $lastKey) use ($planned, $batchSize): void {
                if ($sent % 1_000 < $batchSize || $sent === $planned) {
                    $this->line(sprintf('  %s / %s threads (last id %s)', number_format($sent), number_format($planned), $lastKey));
                }
            },
        );

        if ($result->stop === BackfillStop::NOT_READY) {
            $this->error((string) $result->message);

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s: %s threads sent in %ds. %s tokens, about $%s.',
            $result->succeeded() ? 'Done' : 'Stopped',
            number_format($result->sent),
            $result->seconds,
            number_format($result->tokens),
            number_format($result->costUsd, 4),
        ));

        $resume = $result->lastKey !== null ? 'Continue with --from-id='.((int) $result->lastKey + 1).'.' : 'Run it again.';

        match ($result->stop) {
            BackfillStop::EMBEDDING_FAILED => $this->error('The embedding provider failed on a batch: '.$result->message.'. Nothing from that batch was sent. '.$resume),
            BackfillStop::REJECTED => $this->error('The search engine rejected an indexing task: '.$result->message.'. '.$resume),
            BackfillStop::STALLED => $this->error('The search engine had not caught up with the batches already sent in the time allowed, so the run was stopped before spending more on embeddings. '.$resume),
            BackfillStop::CRASHED => $this->error('The run failed: '.$result->message.'. '.$resume),
            default => null,
        };

        return $result->succeeded() ? self::SUCCESS : self::FAILURE;
    }

    private function intOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
