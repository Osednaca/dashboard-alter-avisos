<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reproducción en vivo</title>
<style nonce="{{ $nonce }}">html,body,#player,iframe{width:100%;height:100%;margin:0;border:0;background:#080d14;overflow:hidden}iframe{display:block}#notice{position:absolute;inset:0;display:grid;place-items:center;color:#fff;font:16px sans-serif;padding:24px;text-align:center;pointer-events:none}#notice:empty{display:none}</style></head>
<body><div id="player"></div><div id="notice">Conectando al directo…</div>
<script nonce="{{ $nonce }}">
const config = {{ Illuminate\Support\Js::from(['provider' => $source['provider'], 'id' => $source['source_id'], 'audio' => $audio, 'origin' => $origin, 'parents' => $parents]) }};
window.signageState = 'connecting';
function state(value, message = '') {
    window.signageState = value;
    document.getElementById('notice').textContent = message;
    window.parent.postMessage({type:'signage-live', state:value, message}, config.origin);
}
window.addEventListener('offline', () => state('offline', 'Sin conexión'));
window.addEventListener('error', () => state('failed', 'No se pudo cargar el reproductor'));
function script(url) { const s=document.createElement('script'); s.src=url; s.onerror=()=>state('failed', 'Proveedor no disponible'); document.head.append(s); }
const minimum = config.provider === 'twitch' ? [400,300] : config.provider === 'youtube' ? [200,200] : [0,0];
if (innerWidth < minimum[0] || innerHeight < minimum[1]) {
    state('failed', 'Zona demasiado pequeña para '+config.provider+'. Usa una zona mayor o pantalla completa.');
} else if (config.provider === 'youtube') {
    window.onYouTubeIframeAPIReady = () => {
        const player = new YT.Player('player', {width:'100%', height:'100%', videoId:config.id,
            playerVars:{autoplay:1, controls:0, playsinline:1, origin:config.origin, disablekb:1},
            events:{onReady:e=>{if(!config.audio)e.target.mute(); e.target.playVideo();},
                onAutoplayBlocked:()=>state('failed','El proveedor bloqueó la reproducción automática'),
                onError:e=>state('failed','YouTube: error '+e.data),
                onStateChange:e=>{if(e.data===1)state('live'); else if(e.data===3)state('buffering','Cargando…'); else if(e.data===0)state('ended','El directo terminó'); else if(e.data===2)state('buffering','Reproducción pausada');}}});
    };
    script('https://www.youtube.com/iframe_api');
} else if (config.provider === 'twitch') {
    const s=document.createElement('script'); s.src='https://player.twitch.tv/js/embed/v1.js';
    s.onerror=()=>state('failed','Twitch no disponible');
    s.onload=()=>{const p=new Twitch.Player('player',{width:'100%',height:'100%',channel:config.id,parent:config.parents,autoplay:true,muted:!config.audio});
        p.addEventListener(Twitch.Player.PLAYING,()=>state('live'));
        p.addEventListener(Twitch.Player.OFFLINE,()=>state('offline','Canal desconectado'));
        p.addEventListener(Twitch.Player.ENDED,()=>state('ended','El directo terminó'));
        p.addEventListener(Twitch.Player.PAUSE,()=>state('buffering','Reproducción pausada'));
        p.addEventListener(Twitch.Player.PLAYBACK_BLOCKED,()=>state('failed','Twitch bloqueó la reproducción automática'));
        setInterval(()=>{if(window.signageState==='live' && p.getPlaybackStats().bufferSize===0)state('buffering','Cargando…');
            else if(window.signageState==='buffering' && !p.isPaused() && p.getPlaybackStats().bufferSize>0)state('live');},2000);
    }; document.head.append(s);
} else {
    const frame=document.createElement('iframe');
    frame.src='https://player.kick.com/'+encodeURIComponent(config.id)+'?autoplay=true&muted='+(!config.audio)+'&allowfullscreen=false';
    frame.allow='autoplay; encrypted-media'; frame.referrerPolicy='strict-origin-when-cross-origin';
    frame.onload=()=>state('unverified'); // Kick documents no playback-status API. Loading is not proof of playback.
    frame.onerror=()=>state('failed','Kick no disponible'); document.getElementById('player').append(frame);
}
</script></body></html>
