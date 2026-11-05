import { Eye, MoreVertical, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';

export function MediaActionsMenu({ filename, onPreview, onRename, onDelete }: {
    filename: string;
    onPreview?: () => void;
    onRename?: () => void;
    onDelete?: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="secondary" size="icon-sm" aria-label={`Opciones de ${filename}`} className="focus-visible:ring-2 focus-visible:ring-accent">
                    <MoreVertical className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {onPreview ? <DropdownMenuItem onSelect={onPreview}><Eye className="size-4" /> Previsualizar</DropdownMenuItem> : null}
                {onRename ? <DropdownMenuItem onSelect={onRename}><Pencil className="size-4" /> Renombrar</DropdownMenuItem> : null}
                {onDelete ? <DropdownMenuItem variant="danger" onSelect={onDelete}><Trash2 className="size-4" /> Eliminar</DropdownMenuItem> : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
