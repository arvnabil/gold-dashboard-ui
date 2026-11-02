# Mulai implementasi dengan coding agent

Letakkan isi paket pada repository proyek. Baca README dan AGENTS.md. Jangan memasukkan credential broker/API ke prompt.

## Prompt pertama

```text
Read AGENTS.md, docs/PRD.md, docs/TEST_PLAN.md and
 docs/IMPLEMENTATION_BACKLOG.md before editing files.

Inspect this repository. Treat the PRD as the implementation contract.
Use Laravel + React/TypeScript + Inertia + shadcn/ui, not Filament.
Keep the Python analysis engine separate from the Windows MT5 collector.
Do not add broker order execution.

Implement only P0-01 to P0-04 in small reviewable steps.
Preserve the specification files, contracts, fixtures and acceptance tests.
If scaffolding into this non-empty repository requires a temporary directory,
use one and merge without overwriting existing documents/tests.

Use synthetic data and mocks. External AI and Telegram calls stay disabled.
Create the missing development scripts, test infrastructure and OpenAPI contract.
Run the schema tests and all relevant new application tests.

Report changed files, exact commands run, test results, missing configuration,
and the next backlog ticket. Do not claim broker/live/AI integration works
unless it was actually tested. Ask before destructive or production actions.
```

## Demo pertama yang harus tercapai

Owner login -> custom shadcn layout -> synthetic feed -> valid closed candle batch -> database -> chart H1 -> health badge.

Sesudah demo tersebut lulus, kerjakan P1 collector dan data integrity. Jangan membangun semua fitur, AI, dan backtest sekaligus.
