# Filagora AI for forums

An optional add-on for [`filagora-forums`](https://github.com/filagora/filagora-forums) and
[`filagora-ai`](https://github.com/filagora/filagora-ai). Install it and the AI chat can find earlier
forum threads, and staff can ask for a suggested answer to a thread. Without it, nothing about the AI
touches the forum.

> **Status: early.** The API may still change before 1.0.

## What it provides

- **Thread vectors.** One vector per answered thread, from its title and first post, in a vector-only
  search index of its own (`thread_vectors`), kept apart from the keyword index of the same threads.
  A thread is worth a vector when it is approved, in an active forum, and answered. The vector is kept
  current as threads and their opening posts change, but not on every reply: only the changes that
  alter whether a thread has a vector or what it says.
- **A place for the chat to look.** A retrieval source (priority 300) offers the threads closest in
  meaning to a question, each as the question plus the replies most likely to hold the answer (the
  first reply from someone other than the asker, the two longest, and the last). What the person asking
  could not open is left out, per person.
- **Thread assist.** `ThreadAssistGate`, `ThreadAssistService` and `ThreadAssistPlan` build a bounded
  view of a thread (the opening post and the newest replies, never a deleted or unapproved post, fenced
  off as data), ground it in whatever the registered retrieval sources hold, and stream the answer.
  Answers are kept per person and replayed free while the thread is unchanged. Routes, views and
  throttles belong to the host.
- **Commands.** `thread-vectors:backfill` (careful, resumable, built on `filagora-ai`'s
  `VectorBackfill`: it checks the provider and the engine before spending anything and stops at the
  first failure) and `thread-vectors:status`.

## Install

```bash
composer require filagora/filagora-ai-forums
php artisan vendor:publish --tag=filagora-ai-forums-config
```

Then, once embeddings are on (`filagora-ai`):

```bash
php artisan filagora:search:sync-settings --index=thread_vectors
php artisan thread-vectors:status
php artisan thread-vectors:backfill --limit=500   # a small trial; prints tokens and cost
php artisan thread-vectors:backfill
```

Set `AI_THREAD_VECTORS_ENABLED=true` and restart the queue workers. Until then threads are not indexed
as they change and the chat does not search them.

## Configuration

`config/filagora-ai-forums.php` is merged with the host's copy, one level deep: a key you set replaces
that key's whole array.

- `gates`: the ability `filagora.ai.thread-assist` maps to the entitlement `operator.ai.thread_assist`.
  Point it at your own key; granting it to members opens thread assist to them (they must also be able
  to open the thread).
- `thread_vectors`: `enabled` (`AI_THREAD_VECTORS_ENABLED`), `first_post_limit`.
- `assist`: `system_prompt`, `source_limits`, `cache_seconds`, `thread_route`.

## Extending

Extend through documented contracts, events and registries. Anything not documented is `@internal`.

## Testing

```bash
composer test
```
