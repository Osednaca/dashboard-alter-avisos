import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';
import { Input } from '@/Components/ui/input';
import { cn } from '@/Utils/cn';

/**
 * Password field with a show/hide toggle. Reused anywhere a password is
 * entered so the affordance stays consistent across the platform.
 */
export function PasswordInput({
    className,
    id,
    type: _type,
    ...props
}: React.InputHTMLAttributes<HTMLInputElement>) {
    const [visible, setVisible] = React.useState(false);
    const generatedId = React.useId();
    const inputId = id ?? generatedId;

    return (
        <div className="relative">
            <Input {...props} id={inputId} type={visible ? 'text' : 'password'} className={cn('pr-12', className)} />
            <button
                type="button"
                onClick={() => setVisible((value) => !value)}
                aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                aria-pressed={visible}
                aria-controls={inputId}
                disabled={props.disabled}
                className="absolute right-0 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded text-faint transition-colors hover:text-fg focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50"
            >
                {visible ? <EyeOff aria-hidden className="size-4" /> : <Eye aria-hidden className="size-4" />}
            </button>
        </div>
    );
}
