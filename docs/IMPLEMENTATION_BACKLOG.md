# Implementation Backlog

**Versi:** 1.0. Urutan berbasis dependency. Estimasi waktu tidak ditentukan sebelum hardware dan repo tersedia.

## 1. Baseline repo yang dituju

```text
xauusd-ai-analyst/
  app/Domain/MarketData/
  app/Domain/TechnicalAnalysis/
  app/Domain/Setups/
  app/Domain/NarrativeAnalysis/
  app/Domain/Notifications/
  app/Domain/Backtesting/
  app/Http/Controllers/
  app/Jobs/
  resources/js/components/ui/
  resources/js/components/trading/
  resources/js/components/analysis/
  resources/js/layouts/
  resources/js/pages/
  services/analysis-engine/src/xau_engine/
  services/analysis-engine/tests/
  services/mt5-collector/
  contracts/
  fixtures/
  tests/Feature/
  tests/Unit/
  tests/contracts/
  tests/engine/
  tests/acceptance/
  tests/e2e/
  docs/
  docker/
  AGENTS.md
  compose.yaml
```

Folder di atas adalah target aplikasi, bukan aplikasi yang sudah disertakan dalam paket PRD.

## 2. Tickets

| Ticket | Deliverable | Dependency | Acceptance/test gate |
| --- | --- | --- | --- |
| P0-01 | Inventaris host, pilihan Windows collector, decision record runtime, lockfiles | - | Catat hardware; dukungan dependency diverifikasi; no secrets |
| P0-02 | Bootstrap Laravel React/Inertia/shadcn, auth, disabled registration, custom layout | P0-01 | SEC-01, SEC-02, UI-01; no Filament dependency |
| P0-03 | Docker dev isolation, PostgreSQL/Redis, worker/scheduler/reverb processes, CI | P0-01 | SEC-08; schema tests pass; test DB terpisah |
| P0-04 | OpenAPI penuh dari contract, types, fixture loaders, test harness | P0-02,P0-03 | Contract drift checks dan test commands tersedia |
| P1-01 | Feed/instrument/config tables, collector tokens, candle ingest + idempotency | P0-04 | DATA-01..05, SEC-03 |
| P1-02 | Revisions, immutable snapshots, outbox, quote ordering | P1-01 | DATA-06,07,12,15 |
| P1-03 | Windows MT5 collector, spool, heartbeat, reconnect/backfill | P1-01 | DATA-08..11,13..16 |
| P1-04 | Basic candle chart, feed health, history pagination | P1-02,P1-03 | UI-01, UI-03; read-only broker parity |
| P2-01 | Decimal helpers, ATR, pivot, structure reducer, BOS/CHoCH/sweep | P1-02 | ENG-01..08; engine adapter tests |
| P2-02 | SND/SNR/FVG/OB detection, availability/evidence | P2-01 | ENG-09..16 |
| P2-03 | Zone lifecycle, event reducer, no-lookahead replay primitives | P2-02 | ENG-17..20, OPS-01, OPS-02 |
| P3-01 | Chart primitives, MTF layers, detail panel, accessibility | P1-04,P2-03 | UI-02, UI-07, UI-08 |
| P3-02 | Setup state machine, scoring, hard gates, deterministic levels | P2-03 | SET-01..08 |
| P3-03 | Private Reverb, sequence handling, polling fallback, historical inspection | P3-01,P3-02 | UI-03..06, SEC-07 |
| P4-01 | AI adapter, request schema, local/semantic guards, fallback, usage logging | P3-02 | AI-01..05, AI-08 |
| P4-02 | Schedule/event triggers, caps, dedupe, owner settings UI | P4-01 | AI-06,07; ALERT-05; UI-06 |
| P4-03 | In-app alerts, Telegram outbox, retry/unknown delivery handling | P3-02,P4-02 | ALERT-01..06 |
| P5-01 | Replay dataset manifests, knowledge/reconstruction modes, chunk checkpoint | P2-03 | OPS-01, OPS-02 |
| P5-02 | Paper fill engine, costs, gap behavior, intrabar ambiguity, metrics | P3-02,P5-01 | OPS-03..06 |
| P5-03 | Replay/paper UI, chronological holdout tooling, result export JSON | P5-02 | Owner can compare immutable runs; no profit claims |
| P6-01 | Security review, network audit, dependency scan, credentials rotation | P4-03,P5-03 | SEC-01..08; no critical findings |
| P6-02 | Fault injection, retention, backup automation, restore runbook | P6-01 | OPS-07..09 |
| P6-03 | Forward observation, UAT signoff, release notes, rollback plan | P6-02 | OPS-10; all MUST requirements verified |

Ranges such as ENG-01..08 mean all cases from that group in TEST_PLAN. They are not a substitute for per-test evidence.

## 3. Format satu issue/PR

```text
Title:
Requirement IDs:
Test IDs:
Input and expected output:
Schema/database change:
Implementation notes:
Failure and retry behavior:
Security impact:
Migration/rollback plan:
Commands actually run:
Results and limitations:
Demo evidence:
```

## 4. Keputusan yang harus dikunci, bukan ditebak

Broker/server/simbol, tick size/digits, price basis, session calendar, actual history coverage, Windows host/VM, API model/provider, budget, Telegram destination, spread threshold, dan backup destination. Simpan status verification dan tanggalnya. Sebelum siap, gunakan fixture dan biarkan dependent feature disabled.

## 5. Urutan review

Data contract -> temporal correctness -> strategy unit tests -> UI fidelity -> failure behavior -> AI explanation -> simulation -> personal release. Tampilan yang menarik bukan bukti data atau strategi sudah benar.
