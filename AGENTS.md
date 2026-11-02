# Agent Guidelines & Operational Boundary

This repository is governed by the specifications in `docs/PRD.md`, `docs/TEST_PLAN.md`, and `docs/IMPLEMENTATION_BACKLOG.md`. All coding agents and contributors must strictly adhere to the following rules:

## 1. Absolute Boundaries (Hard Invariants)
- **NO Order Execution**: Under no circumstances should order placement, execution endpoints (`order_send`), automated trading, copy trading, martingale, or grid execution logic be added. The product is strictly for analysis, alerts, replay, and paper simulation.
- **Architectural Separation**:
  - **Laravel (Modular Monolith)**: Handles auth, API endpoints, Postgres persistence, queue dispatch, immutable snapshots, Reverb WebSocket broadcasting, and AI orchestration.
  - **Frontend**: React + TypeScript + Inertia.js + Tailwind CSS + shadcn/ui (no Filament, no iframe TradingView).
  - **Technical Engine**: Deterministic Python FastAPI service. Must not access the broker, LLM, or main database directly.
  - **Collector**: Dedicated MT5 collector running on Windows. Handles reading MT5, normalizing, spooling, retry, and sending closed candles/quotes to Laravel ingest.
- **Secrets & Credentials**: Never commit `.env` files, broker credentials, API keys, or Telegram bot tokens. All testing must use mocks and synthetic fixtures.

## 2. Implementation Methodology
- Implement in small, reviewable increments following the tickets in `docs/IMPLEMENTATION_BACKLOG.md` (P0-01 through P0-04 for Phase 0).
- Preserve existing specification files, contracts, fixtures, and acceptance tests.
- When scaffolding new services or tools, do not overwrite specification documents.
- Any new features must be accompanied by relevant unit/integration tests complying with `docs/TEST_PLAN.md`.

## 3. Technology Stack Baseline
- **Backend**: Laravel 11/12/13 (PHP 8.3+), PostgreSQL (primary truth), Redis (queue/cache/locks), Laravel Reverb (WebSocket).
- **Frontend**: React 18/19, TypeScript, Inertia.js, Tailwind CSS, shadcn/ui, Lightweight Charts.
- **Python Engine**: Python 3.12+, FastAPI, NumPy/Pandas/Pydantic for deterministic strategy calculations.
- **Collector**: Python 3.12+ with MetaTrader5 package on Windows.

