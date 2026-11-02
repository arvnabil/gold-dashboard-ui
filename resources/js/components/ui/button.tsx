import * as React from 'react';
import { cn } from '@/lib/utils';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  size?: 'default' | 'sm' | 'lg' | 'icon';
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'default', size = 'default', ...props }, ref) => {
    const base = 'inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-amber-500 disabled:pointer-events-none disabled:opacity-50';
    
    const variants = {
      default: 'bg-amber-500 text-slate-950 shadow hover:bg-amber-400 font-semibold',
      destructive: 'bg-red-600 text-slate-50 shadow-sm hover:bg-red-700',
      outline: 'border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-100',
      secondary: 'bg-slate-800 text-slate-100 shadow-sm hover:bg-slate-700',
      ghost: 'hover:bg-slate-800 hover:text-slate-100 text-slate-400',
      link: 'text-amber-400 underline-offset-4 hover:underline',
    };

    const sizes = {
      default: 'h-9 px-4 py-2',
      sm: 'h-8 rounded-md px-3 text-xs',
      lg: 'h-10 rounded-md px-8',
      icon: 'h-9 w-9',
    };

    return (
      <button
        ref={ref}
        className={cn(base, variants[variant], sizes[size], className)}
        {...props}
      />
    );
  }
);
Button.displayName = 'Button';

