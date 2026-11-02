import { Badge } from '@/components/ui/badge';
import { Activity, AlertTriangle, CheckCircle2, Clock, PauseCircle, WifiOff } from 'lucide-react';

export type HealthStatus = 'LIVE' | 'STALE' | 'DEGRADED' | 'DISCONNECTED' | 'WARMING_UP' | 'MARKET_CLOSED';

interface HealthBadgeProps {
  status: HealthStatus;
  lastQuoteAgeSeconds?: number;
}

export function HealthBadge({ status, lastQuoteAgeSeconds }: HealthBadgeProps) {
  switch (status) {
    case 'LIVE':
      return (
        <Badge variant="success" className="gap-1.5 py-1">
          <span className="relative flex h-2 w-2">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
          </span>
          LIVE
          {lastQuoteAgeSeconds !== undefined && lastQuoteAgeSeconds > 0 && (
            <span className="text-[10px] text-emerald-300 font-mono">({lastQuoteAgeSeconds}s)</span>
          )}
        </Badge>
      );
    case 'STALE':
      return (
        <Badge variant="warning" className="gap-1.5 py-1">
          <Clock className="w-3.5 h-3.5" />
          STALE
          {lastQuoteAgeSeconds !== undefined && (
            <span className="text-[10px] font-mono">({lastQuoteAgeSeconds}s)</span>
          )}
        </Badge>
      );
    case 'DEGRADED':
      return (
        <Badge variant="warning" className="gap-1.5 py-1">
          <AlertTriangle className="w-3.5 h-3.5" />
          DEGRADED
        </Badge>
      );
    case 'DISCONNECTED':
      return (
        <Badge variant="destructive" className="gap-1.5 py-1">
          <WifiOff className="w-3.5 h-3.5" />
          DISCONNECTED
        </Badge>
      );
    case 'WARMING_UP':
      return (
        <Badge variant="secondary" className="gap-1.5 py-1">
          <Activity className="w-3.5 h-3.5 animate-pulse text-amber-400" />
          WARMING UP
        </Badge>
      );
    case 'MARKET_CLOSED':
      return (
        <Badge variant="outline" className="gap-1.5 py-1">
          <PauseCircle className="w-3.5 h-3.5 text-slate-400" />
          MARKET CLOSED
        </Badge>
      );
    default:
      return (
        <Badge variant="secondary" className="gap-1">
          {status}
        </Badge>
      );
  }
}

