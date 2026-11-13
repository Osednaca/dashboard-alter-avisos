import type { ReactNode } from 'react';
import { cn } from '@/Utils/cn';

export function PreviewFrame({ children, led = false, portrait = false, rotation, aspect = '16 / 9' }: {
    children: ReactNode; led?: boolean; portrait?: boolean; rotation?: number; aspect?: string;
}) {
    return <div data-preview-presentation={led ? 'led' : 'screen'} className={cn(
        'relative mx-auto w-full',
        led ? 'rounded-[3px] border border-[#48515b] bg-[#20272d] p-2 shadow-[inset_0_1px_0_#66717a,0_8px_18px_-10px_#000]'
            : 'rounded-[20px] border border-line-strong bg-[#04080d] p-2.5 shadow-float',
        !led && portrait ? 'max-w-[280px]' : '',
    )}>
        {led ? <div aria-hidden="true">
            {['left-[3px] top-[3px]', 'right-[3px] top-[3px]', 'bottom-[3px] left-[3px]', 'bottom-[3px] right-[3px]'].map(position => (
                <span key={position} className={cn('absolute size-1 rounded-full bg-[#8b949b] shadow-[inset_0_-1px_1px_#000]', position)} />
            ))}
        </div> : null}
        <div data-preview-orientation={led || !portrait ? 'landscape' : 'portrait'} data-preview-rotation={rotation}
            className={cn('relative flex overflow-hidden bg-black', led ? 'rounded-[1px]' : 'rounded-[12px]')}
            style={{ aspectRatio: led ? '3 / 1' : aspect }}>
            {children}
            {led ? <div aria-hidden="true" data-preview-led-matrix="true" className="pointer-events-none absolute inset-0 z-[1]"
                style={{
                    backgroundImage: 'radial-gradient(circle, transparent 1px, rgb(0 0 0 / 0.55) 1.3px), radial-gradient(circle, rgb(150 170 180 / 0.14) 0.7px, transparent 1px), linear-gradient(to right, rgb(0 0 0 / 0.65) 1px, transparent 1px)',
                    backgroundSize: '4px 4px, 4px 4px, 33.333% 100%',
                }} /> : null}
        </div>
    </div>;
}

