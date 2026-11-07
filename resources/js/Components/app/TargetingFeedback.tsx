export function TargetingFeedback({ error }: { error?: string }) {
    return <>
        {error ? <p role="alert" className="mb-2 text-xs text-danger">{error}</p> : null}
        <p className="mb-2 text-xs text-muted">Las reglas de inclusión se combinan con «o»; las exclusiones restan. Si solo hay exclusiones, se incluyen todos los avisos salvo los excluidos.</p>
    </>;
}
