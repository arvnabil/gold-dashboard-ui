import React, { ReactNode } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { HealthBadge, HealthStatus } from '@/components/trading/HealthBadge';
import { 
  BarChart3, 
  TrendingUp, 
  Sliders, 
  History, 
  ShieldAlert, 
  LogOut, 
  Layers, 
  Activity,
  Cpu
} from 'lucide-react';

interface AppLayoutProps {
  children: ReactNode;
  title?: string;
  feedStatus?: HealthStatus;
  feedSymbol?: string;
  bidPrice?: string;
  askPrice?: string;
  lastQuoteAge?: number;
}

export function AppLayout({
  children,
  title = 'XAUUSD AI Hybrid Analyst',
  feedStatus = 'LIVE',
  feedSymbol = 'XAUUSD (Gold)',
  bidPrice = '2736.80',
  askPrice = '2737.05',
  lastQuoteAge = 1,
}: AppLayoutProps) {
  const { auth, url } = usePage<any>().props;

  const handleLogout = () => {
    router.post('/logout');
  };

  const navItems = [
    { label: 'Overview', href: '/', icon: BarChart3 },
    { label: 'Chart & SMC Analysis', href: '/chart', icon: TrendingUp },
    { label: 'Trade Setups', href: '/setups', icon: Layers },
    { label: 'AI Narrative Reports', href: '/reports', icon: Cpu },
    { label: 'Replay / Simulation', href: '/replay', icon: History },
    { label: 'System Health', href: '/health', icon: Activity },
    { label: 'Settings', href: '/settings', icon: Sliders },
  ];

  return (
    <div className="min-h-screen bg-[#070b12] text-slate-100 flex flex-col font-sans">
      {/* Top Navbar */}
      <header className="h-14 border-b border-slate-800 bg-slate-950/90 backdrop-blur sticky top-0 z-50 px-4 flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Link href="/" className="flex items-center space-x-2">
            <span className="flex h-7 w-7 items-center justify-center rounded bg-amber-500/10 border border-amber-500/30 text-amber-400 font-bold text-xs tracking-wider">
              XAU
            </span>
            <span className="font-semibold text-sm tracking-tight text-slate-100 hidden sm:inline-block">
              AI Hybrid Analyst <span className="text-[10px] text-amber-500/80 font-mono ml-1 px-1.5 py-0.5 rounded bg-amber-500/10 border border-amber-500/20">v1.0</span>
            </span>
          </Link>

          <div className="h-4 w-[1px] bg-slate-800 hidden md:block" />

          {/* Instrument & Price Snapshot */}
          <div className="flex items-center space-x-3 text-xs">
            <span className="text-slate-300 font-medium">{feedSymbol}</span>
            <div className="flex items-center space-x-1.5 bg-slate-900 border border-slate-800 rounded px-2 py-0.5 font-mono">
              <span className="text-slate-400 text-[11px]">BID</span>
              <span className="text-emerald-400 font-semibold">{bidPrice}</span>
              <span className="text-slate-600">/</span>
              <span className="text-slate-400 text-[11px]">ASK</span>
              <span className="text-emerald-400 font-semibold">{askPrice}</span>
            </div>
            <HealthBadge status={feedStatus} lastQuoteAgeSeconds={lastQuoteAge} />
          </div>
        </div>

        {/* Right Header: User & Boundaries info */}
        <div className="flex items-center space-x-3">
          <div className="hidden lg:flex items-center space-x-1.5 text-[11px] text-slate-400 bg-slate-900/50 border border-slate-800 px-2 py-1 rounded">
            <ShieldAlert className="w-3.5 h-3.5 text-amber-500" />
            <span>Strict Analysis Only (No Execution)</span>
          </div>

          {auth?.user && (
            <div className="flex items-center space-x-2 pl-2 border-l border-slate-800">
              <span className="text-xs text-slate-300 font-medium hidden md:inline">
                {auth.user.name} ({auth.user.role})
              </span>
              <button
                onClick={handleLogout}
                className="text-slate-400 hover:text-red-400 p-1.5 rounded hover:bg-slate-800/80 transition-colors"
                title="Logout"
              >
                <LogOut className="w-4 h-4" />
              </button>
            </div>
          )}
        </div>
      </header>

      {/* Main Sub-nav & Content Area */}
      <div className="flex-1 flex flex-col md:flex-row">
        {/* Navigation Sidebar */}
        <aside className="w-full md:w-56 bg-slate-950/40 border-r border-slate-800/80 p-3 flex md:flex-col justify-between shrink-0">
          <nav className="flex md:flex-col gap-1 overflow-x-auto md:overflow-visible">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = url === item.href || (item.href !== '/' && url.startsWith(item.href));
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`flex items-center space-x-2 px-3 py-2 rounded-md text-xs font-medium transition-colors whitespace-nowrap ${
                    isActive
                      ? 'bg-amber-500/10 text-amber-400 border border-amber-500/30'
                      : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900/60'
                  }`}
                >
                  <Icon className={`w-4 h-4 ${isActive ? 'text-amber-400' : 'text-slate-400'}`} />
                  <span>{item.label}</span>
                </Link>
              );
            })}
          </nav>

          <div className="hidden md:block pt-4 border-t border-slate-800/60 text-[11px] text-slate-500 px-2">
            <div>Deployment: Ubuntu / Local</div>
            <div className="text-[10px] text-slate-600 mt-1">UTC Timezone Canonical</div>
          </div>
        </aside>

        {/* Content Body */}
        <main className="flex-1 p-4 md:p-6 overflow-y-auto">
          {children}
        </main>
      </div>
    </div>
  );
}

