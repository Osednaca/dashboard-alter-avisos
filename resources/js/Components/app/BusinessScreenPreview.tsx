import { ScreenPreview } from './ScreenPreview';
import type { DevicePreviewData } from '@/Types/preview';

export type BusinessPreviewData = DevicePreviewData;

export function BusinessScreenPreview({ preview, className }: { preview: BusinessPreviewData | null; className?: string }) {
    return <ScreenPreview preview={preview} className={className} />;
}
