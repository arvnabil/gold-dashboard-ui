# ADR 001: Host Inventory & Runtime Decision Record (P0-01)

## Status
Accepted

## Context
Project **XAUUSD AI Hybrid Analyst v1.0** requires a robust foundation for multi-timeframe technical analysis (M5, M15, H1, H4, D1) and AI-driven narrative synthesis without order execution capabilities.
The deployment topology consists of:
1. **Ubuntu Home Server (Target Production & Core Services)**:
   - Reverse proxy (HTTPS)
   - Laravel application (Modular Monolith, PHP 8.3+)
   - PostgreSQL (primary database of record, strict constraints)
   - Redis (queue, cache, distributed locking)
   - Laravel Reverb (WebSocket server)
   - Python FastAPI Engine (deterministic technical analysis)
2. **Windows Host / VM (Dedicated MT5 Collector)**:
   - MetaTrader 5 Terminal
   - Python collector with local durable disk spool
   - Authenticated HTTPS connection to Laravel ingest endpoints over Tailscale

## Host & Tooling Verification
- **Development OS**: Windows 11 (build / workspace)
- **PHP**: PHP 8.3.29 (CLI)
- **Composer**: 2.9.5
- **Node / JS Runtime**: Bun 1.3.9 (high-performance package manager and JS/TS runtime)
- **Docker**: Docker 29.9.2 with Docker Compose v2
- **Version Control**: Git 2.53.0

## Invariants & Security
- `NO_EXECUTION_BOUNDARY`: No automated trading or broker order execution code allowed.
- `NO_SECRETS_COMMITTED`: `.env` files and broker credentials remain strictly uncommitted.
- Synthetic fixtures and mocks will be utilized for all contract and pipeline validation.

