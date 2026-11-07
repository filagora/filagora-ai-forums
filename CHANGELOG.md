# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Thread vectors: the vector-only `thread_vectors` index, kept current as threads and their opening posts change, and `thread-vectors:backfill` and `thread-vectors:status`.
- A chat retrieval source for earlier threads (priority 300), visibility-checked per person, with `ThreadExcerptBuilder` choosing the replies shown.
- Thread assist: `ThreadAssistGate`, `ThreadAssistService`, `ThreadAssistPlan` and the ability `filagora.ai.thread-assist` (entitlement `operator.ai.thread_assist`).
