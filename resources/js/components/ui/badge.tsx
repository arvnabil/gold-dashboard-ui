import * as React from 'react';
import { cn } from '@/lib/utils';

export interface BadgeProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning';
}

export function Badge({ className, variant = 'default', ...props }: BadgeProps) {
  const variants = {
    default: 'border-transparent bg-amber-500/20 text-amber-400 border border-amber-500/30',
    secondary: 'border-transparent bg-slate-800 text-slate-300',
    destructive: 'border-transparent bg-red-950/60 text-red-400 border border-red-800/40',
    outline: 'text-slate-400 border border-slate-700',
    success: 'border-transparent bg-emerald-950/60 text-emerald-400 border border-emerald-800/40',
    warning: 'border-transparent bg-yellow-950/60 text-yellow-400 border border-yellow-800/40',
  };

  return (
    <div
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wide transition-colors',
        variants[variant],
        className
      )}
      {...props}
    />
  );
}

