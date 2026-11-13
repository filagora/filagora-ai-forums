# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.1.1] - 2026-11-13

### Changed

- The README follows the layout shared by every Filagora package (What it provides, Requirements, Install, Configuration, Extending, Testing, Local development, Licence), so the docs page built from it reads the same as the others. No code changes.

## [0.1.0] - 2026-11-07

### Fixed

- Nothing is built while the application boots: the thread retrieval source resolves the forum access rules when asked, and the thread vector sync when a change happens. Building the access rules at boot broke a host's staff preview.

### Added

- Thread vectors: the vector-only `thread_vectors` index, kept current as threads and their opening posts change, and `thread-vectors:backfill` and `thread-vectors:status`.
- A chat retrieval source for earlier threads (priority 300), visibility-checked per person, with `ThreadExcerptBuilder` choosing the replies shown.
- Thread assist: `ThreadAssistGate`, `ThreadAssistService`, `ThreadAssistPlan` and the ability `filagora.ai.thread-assist` (entitlement `operator.ai.thread_assist`).
