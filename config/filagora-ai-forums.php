<?php

declare(strict_types=1);

/*
 * Environment variable names are kept from the application this package was extracted from, so an
 * existing `.env` keeps working.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Gate abilities
    |--------------------------------------------------------------------------
    |
    | Maps the ability that guards thread assist to the entitlement that allows it. Point it at a
    | different entitlement to use your own keys. Opening thread assist to members later is a matter
    | of granting this entitlement to them (the person must also be able to open the thread).
    |
    */
    'gates' => [
        'filagora.ai.thread-assist' => 'operator.ai.thread_assist',
    ],

    /*
    |--------------------------------------------------------------------------
    | Thread vectors
    |--------------------------------------------------------------------------
    |
    | One vector per answered thread, from its title and first post, so the chat can find earlier
    | threads. Off by default so that installing the package changes nothing until the index exists
    | and has been filled: while off, threads are not indexed as they change, the chat does not search
    | them, and the health check ignores the index. `thread-vectors:backfill` works regardless.
    |
    */
    'thread_vectors' => [
        'enabled' => (bool) env('AI_THREAD_VECTORS_ENABLED', false),
        // How much of the opening post is embedded, after the title.
        'first_post_limit' => 1500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Thread assist
    |--------------------------------------------------------------------------
    |
    | `system_prompt` opens the instructions the model is given (who it is helping and for what); unset,
    | a generic one is used. `source_limits` is how many excerpts of each retrieval source a request
    | takes: a thread plus a full set of excerpts made a prompt the model took a minute and a half to
    | start answering. Answers are kept `cache_seconds` per person (what is found depends on what that
    | person may see) and replayed free while the thread is unchanged. `thread_route` is the named
    | route of the thread page (it receives `thread`).
    |
    */
    'assist' => [
        'system_prompt' => null,
        'source_limits' => ['code' => 4, 'docs' => 3, 'threads' => 3, 'plugins' => 2],
        'cache_seconds' => 21_600,
        'thread_route' => null,
    ],

];
