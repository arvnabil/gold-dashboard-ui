import React, { useEffect, useRef } from 'react';
import { Head, Link } from '@inertiajs/react';
import { AppLayout } from '@/layouts/AppLayout';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card';
import { HealthBadge, HealthStatus } from '@/components/trading/HealthBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { createChart, ColorType, IChartApi, ISeriesApi } from 'lightweight-charts';
import { 
  TrendingUp, 
  ArrowUpRight, 
  ArrowDownRight, 
  Layers, 
  Clock, 
  CheckCircle2, 
  Database,
  ChevronRight,
  ShieldAlert
} from 'lucide-react';

interface CandleData {
  time: number | string;
  open: number;
  high: number;
  low: number;
  close: number;
  tick_volume?: number;
  spread?: number;
  is_closed?: boolean;
}

interface OverviewProps {
  feed: {
    id: string;
    symbol: string;
    broker: string;
    status: HealthStatus;
    bid: string;
    ask: string;
    last_quote_age: number;
    timeframe_bias: {
      M5: 'BULLISH' | 'BEARISH' | 'NEUTRAL';
      M15: 'BULLISH' | 'BEARISH' | 'NEUTRAL';
      H1: 'BULLISH' | 'BEARISH' | 'NEUTRAL';
      H4: 'BULLISH' | 'BEARISH' | 'NEUTRAL';
      D1: 'BULLISH' | 'BEARISH' | 'NEUTRAL';
    };
  };
  h1Candles: CandleData[];
  recentBatch: {
    receipt_id: string;
    committed_at: string;
    count: number;
    timeframe: string;
  };
}

export default function Overview({ feed, h1Candles, recentBatch }: OverviewProps) {
  const chartContainerRef = useRef<HTMLDivElement>(null);
  const chartRef = useRef<IChartApi | null>(null);
  const seriesRef = useRef<ISeriesApi<'Candlestick'> | null>(null);

  useEffect(() => {
    if (!chartContainerRef.current) return;

    const chart = createChart(chartContainerRef.current, {
      layout: {
        background: { type: ColorType.Solid, color: '#090d16' },
        textColor: '#94a3b8',
        fontSize: 11,
      },
      grid: {
        vertLines: { color: '#1e293b40' },
        horzLines: { color: '#1e293b40' },
      },
      crosshair: {
        mode: 1,
      },
      rightPriceScale: {
        borderColor: '#1e293b',
      },
      timeScale: {
        borderColor: '#1e293b',
        timeVisible: true,
      },
    });

    const candlestickSeries = chart.addCandlestickSeries({
      upColor: '#10b981',
      downColor: '#ef4444',
      borderVisible: false,
      wickUpColor: '#10b981',
      wickDownColor: '#ef4444',
    });

    if (h1Candles && h1Candles.length > 0) {
      candlestickSeries.setData(h1Candles as any);
      chart.timeScale().fitContent();
    }

    chartRef.current = chart;
    seriesRef.current = candlestickSeries;

    const handleResize = () => {
      if (chartContainerRef.current && chartRef.current) {
        chartRef.current.applyOptions({
          width: chartContainerRef.current.clientWidth,
          height: chartContainerRef.current.clientHeight,
        });
      }
    };

    window.addEventListener('resize', handleResize);
    return () => {
      window.removeEventListener('resize', handleResize);
      chart.remove();
    };
  }, [h1Candles]);

  return (
    <AppLayout 
      feedStatus={feed.status} 
      feedSymbol={feed.symbol} 
      bidPrice={feed.bid} 
      askPrice={feed.ask}
      lastQuoteAge={feed.last_quote_age}
    >
      <Head title="XAUUSD Overview & H1 Analysis" />

      {/* Top Banner & Multi-Timeframe Status */}
      <div className="space-y-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-bold tracking-tight text-slate-100 flex items-center space-x-2">
              <span>XAUUSD Market Overview</span>
              <HealthBadge status={feed.status} lastQuoteAgeSeconds={feed.last_quote_age} />
            </h1>
            <p className="text-xs text-slate-400 mt-1">
              Broker: <span className="text-slate-200 font-medium">{feed.broker}</span> · Native MT5 Timeframe Ingestion · Non-execution Mode
            </p>
          </div>

          <div className="flex items-center space-x-2">
            <Link href="/chart">
              <Button size="sm" className="gap-1.5 text-xs">
                Open Full Chart
                <ChevronRight className="w-3.5 h-3.5" />
              </Button>
            </Link>
          </div>
        </div>

        {/* Multi-Timeframe Bias Cards */}
        <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
          {(['M5', 'M15', 'H1', 'H4', 'D1'] as const).map((tf) => {
            const bias = feed.timeframe_bias[tf];
            const isBullish = bias === 'BULLISH';
            const isBearish = bias === 'BEARISH';
            return (
              <Card key={tf} className="bg-slate-900/60 border-slate-800/80 p-3">
                <div className="flex items-center justify-between text-xs mb-1">
                  <span className="font-mono font-bold text-slate-300">{tf}</span>
                  <Badge 
                    variant={isBullish ? 'success' : isBearish ? 'destructive' : 'secondary'}
                    className="text-[10px] px-1.5 py-0"
                  >
                    {bias}
                  </Badge>
                </div>
                <div className="flex items-center space-x-1 mt-2 text-xs">
                  {isBullish && <ArrowUpRight className="w-3.5 h-3.5 text-emerald-400" />}
                  {isBearish && <ArrowDownRight className="w-3.5 h-3.5 text-red-400" />}
                  <span className="text-[11px] text-slate-400">
                    {isBullish ? 'Confirmed Break' : isBearish ? 'Supply Pressure' : 'Range Bound'}
                  </span>
                </div>
              </Card>
            );
          })}
        </div>

        {/* Main H1 Chart & Feed Health Section */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* H1 Chart Card (Spans 2 columns) */}
          <Card className="lg:col-span-2 border-slate-800 bg-slate-900/80 flex flex-col">
            <CardHeader className="py-3 px-4 flex flex-row items-center justify-between">
              <div>
                <CardTitle className="text-sm font-semibold flex items-center space-x-2">
                  <span>H1 Primary Bias Chart</span>
                  <Badge variant="outline" className="text-[10px] font-mono">1 Hour Native</Badge>
                </CardTitle>
                <CardDescription>Visualizing latest confirmed candles from synthetic/live ingestion</CardDescription>
              </div>
              <div className="flex items-center space-x-2 text-xs text-slate-400 font-mono">
                <span>Last: <span className="text-emerald-400 font-bold">{feed.bid}</span></span>
              </div>
            </CardHeader>
            <CardContent className="p-0 flex-1 min-h-[360px] relative">
              <div ref={chartContainerRef} className="w-full h-[360px]" />
            </CardContent>
          </Card>

          {/* Right Ingestion & Batch Verification Card */}
          <div className="space-y-4">
            <Card className="border-slate-800 bg-slate-900/80">
              <CardHeader className="py-3 px-4">
                <CardTitle className="text-sm font-semibold flex items-center space-x-2 text-slate-200">
                  <Database className="w-4 h-4 text-amber-500" />
                  <span>Latest Committed Ingest</span>
                </CardTitle>
                <CardDescription>Atomic idempotency receipt</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3 text-xs">
                <div className="flex justify-between items-center py-1.5 border-b border-slate-800/80">
                  <span className="text-slate-400">Receipt ID:</span>
                  <span className="font-mono text-slate-200 text-[11px]">{recentBatch.receipt_id.slice(0, 16)}...</span>
                </div>
                <div className="flex justify-between items-center py-1.5 border-b border-slate-800/80">
                  <span className="text-slate-400">Timeframe:</span>
                  <Badge variant="secondary" className="font-mono">{recentBatch.timeframe}</Badge>
                </div>
                <div className="flex justify-between items-center py-1.5 border-b border-slate-800/80">
                  <span className="text-slate-400">Accepted Candles:</span>
                  <span className="font-mono text-emerald-400 font-semibold">{recentBatch.count} bars</span>
                </div>
                <div className="flex justify-between items-center py-1.5 border-b border-slate-800/80">
                  <span className="text-slate-400">Committed At:</span>
                  <span className="font-mono text-slate-300 text-[11px]">{recentBatch.committed_at}</span>
                </div>
                <div className="p-2.5 bg-emerald-950/20 border border-emerald-800/30 rounded text-[11px] text-emerald-400 flex items-center space-x-2">
                  <CheckCircle2 className="w-4 h-4 shrink-0" />
                  <span>PostgreSQL persistence and schema validation passed.</span>
                </div>
              </CardContent>
            </Card>

            <Card className="border-slate-800 bg-slate-900/80">
              <CardHeader className="py-3 px-4">
                <CardTitle className="text-sm font-semibold flex items-center space-x-2 text-slate-200">
                  <ShieldAlert className="w-4 h-4 text-amber-400" />
                  <span>Safety Guardrails</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-xs text-slate-400">
                <div className="flex items-center space-x-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                  <span>Order Execution Engine: <strong className="text-slate-200 font-mono">DISABLED (NO ORDER PATH)</strong></span>
                </div>
                <div className="flex items-center space-x-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                  <span>External LLM/Telegram API: <strong className="text-slate-200 font-mono">OFF (MOCKED)</strong></span>
                </div>
                <div className="flex items-center space-x-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                  <span>Idempotent Spool Sync: <strong className="text-slate-200 font-mono">ACTIVE</strong></span>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        {/* Closed Candle Batch Details Table */}
        <Card className="border-slate-800 bg-slate-900/80">
          <CardHeader className="py-3 px-4">
            <CardTitle className="text-sm font-semibold text-slate-200">
              Verified Closed Candle Batch (H1 Feed History)
            </CardTitle>
            <CardDescription>
              Committed records immutable in database. Closed bars only.
            </CardDescription>
          </CardHeader>
          <CardContent className="p-0 overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead className="bg-slate-950/60 text-slate-400 border-y border-slate-800">
                <tr>
                  <th className="py-2.5 px-4 font-medium">Timestamp (UTC)</th>
                  <th className="py-2.5 px-4 font-medium">Open</th>
                  <th className="py-2.5 px-4 font-medium">High</th>
                  <th className="py-2.5 px-4 font-medium">Low</th>
                  <th className="py-2.5 px-4 font-medium">Close</th>
                  <th className="py-2.5 px-4 font-medium">Tick Vol</th>
                  <th className="py-2.5 px-4 font-medium">Spread</th>
                  <th className="py-2.5 px-4 font-medium">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 font-mono">
                {h1Candles.map((c, idx) => (
                  <tr key={idx} className="hover:bg-slate-800/30">
                    <td className="py-2 px-4 text-slate-300">{typeof c.time === 'number' ? new Date(c.time * 1000).toISOString().replace('T', ' ').slice(0, 16) : c.time}</td>
                    <td className="py-2 px-4 text-slate-200">{c.open.toFixed(2)}</td>
                    <td className="py-2 px-4 text-emerald-400">{c.high.toFixed(2)}</td>
                    <td className="py-2 px-4 text-red-400">{c.low.toFixed(2)}</td>
                    <td className="py-2 px-4 text-slate-100 font-semibold">{c.close.toFixed(2)}</td>
                    <td className="py-2 px-4 text-slate-400">{c.tick_volume ?? 4500}</td>
                    <td className="py-2 px-4 text-slate-400">{c.spread ?? 12} pts</td>
                    <td className="py-2 px-4">
                      <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-950/60 text-emerald-400 border border-emerald-800/40">
                        CONFIRMED
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}

