# MT5 Collector Service (Windows Host)

This dedicated Python service runs natively on Windows where MetaTrader 5 terminal is installed.

## Responsibilities
- Polls quotes and closed candles from MT5 Terminal.
- Normalizes data to canonical UTC timestamps and decimal representations.
- Durable local disk spooling (`./spool/`) for offline resilience.
- Idempotent batch POST to Laravel backend at `/api/v1/collector/batches`.
- Telemetry heartbeat POST to `/api/v1/collector/heartbeat`.

## Strict Hard Invariant
- **READ-ONLY / TELEMETRY ONLY**: No order execution (`OrderSend`, `order_send`), position modification, or trading commands are present or permitted.

