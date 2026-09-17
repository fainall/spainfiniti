<?php
/**
 * Mariet responde con nota de voz — Spa Infinity
 *
 * Solo cuando el cliente lo pide ("mándame un audio", "respóndeme por voz").
 * Desde ahí le sigue contestando en audio en ese chat hasta que pida texto o
 * pasen 24 horas sin hablar.
 *
 * La voz sale de ElevenLabs si hay clave en bot-config.php (elevenKey, y
 * opcionalmente elevenVoiceId y elevenModel). Si no hay, se usa la voz de
 * OpenAI, que ya está configurada: así esto funciona igual desde el primer día.
 */
require_once __DIR__ . '/wa-pausas.php';

const WA_VOZ_MAX = 850;             // más largo que esto no se manda en audio
const WA_VOZ_VENCE = 24 * 3600;     // la preferencia dura un día sin hablar

/* ── ¿Me lo está pidiendo en audio? ── */
function wa_pide_audio($texto) {
    $t = ' ' . trim(preg_replace('/\s+/', ' ', strtolower(strtr((string)$texto,
        ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n'])))) . ' ';
    if (!preg_match_all('/\b(audios?|voz|vocal|grabacion)\b/', $t, $m, PREG_OFFSET_CAPTURE)) return false;
    /* que sea un pedido y no un comentario ("escuché un audio en TikTok"): el
       verbo de pedir tiene que estar pegado a la palabra audio */
    $pedir = '/\b(mandame|mandamelo|manda|mandas|mandalo|enviame|envia|envias|responde|respondeme|contesta|contestame|'
           . 'hablame|habla|dime|dilo|explicame|cuentame|grabame|graba|grabas|prefiero|mejor|quiero|quisiera|gustaria|'
           . 'puedes|podrias|podes|se puede|porfa|por favor)\b/';
    foreach ($m[1] as $hit) {
        $ini = max(0, $hit[1] - 34);
        if (preg_match($pedir, substr($t, $ini, $hit[1] - $ini + strlen($hit[0]) + 22))) return true;
    }
    return false;
}
function wa_pide_texto($texto) {
    $t = ' ' . strtolower(strtr((string)$texto, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u'])) . ' ';
    /* tiene que ser un "no me mandes audios", no un "no entendí, mándame un audio" */
    if (preg_match('/\b(no|nada de|ya no|deja de|dejen de|basta de)\s+(me\s+|nos\s+)?(mandes|manden|mande|mandar|envies|envien|enviar|hables|hablen|hablar|grabes|graben|quiero|queremos)?\s*(mas\s+)?(audios?|notas de voz|voz)\b/', $t)) return true;
    return (bool)preg_match('/\b(escribeme|escribemelo|escrito|por escrito|por texto|en texto|mensaje escrito)\b/', $t);
}

/* ── Preferencia por chat, guardada junto a las pausas ── */
function wa_voz_activa($tel) {
    $c = is_file(WA_CONTROL_ARCHIVO) ? json_decode((string)file_get_contents(WA_CONTROL_ARCHIVO), true) : [];
    $v = (is_array($c) && is_array($c['voz'] ?? null)) ? $c['voz'] : [];
    $hasta = (int)($v[wa_tel($tel)] ?? 0);
    return $hasta > time();
}
function wa_voz_marcar($tel, $encendida) {
    @mkdir(dirname(WA_CONTROL_ARCHIVO), 0755, true);
    $fp = @fopen(WA_CONTROL_ARCHIVO, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    $c = json_decode((string)stream_get_contents($fp), true);
    $c = is_array($c) ? $c : [];
    $c['voz'] = is_array($c['voz'] ?? null) ? $c['voz'] : [];
    if ($encendida) $c['voz'][wa_tel($tel)] = time() + WA_VOZ_VENCE;
    else unset($c['voz'][wa_tel($tel)]);
    foreach ($c['voz'] as $t => $h) if ((int)$h < time()) unset($c['voz'][$t]);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($c, JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);
}

/* ── El texto, listo para leerse en voz alta ──
   Los emojis y las viñetas suenan raro o se leen literal. */
function wa_voz_texto_limpio($texto) {
    $t = (string)$texto;
    $t = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}]/u', '', $t);
    $t = preg_replace('/^[\s\-\*•\d]+[\.\)]?\s*/mu', '', $t);
    $t = preg_replace('/\*+/', '', $t);
    $t = preg_replace('/\s*\n+\s*/u', '. ', $t);
    $t = preg_replace('/\.\s*\./u', '.', $t);
    $t = preg_replace('/\s+/u', ' ', $t);
    return trim($t);
}

/* ── Generar la voz ── */
function wa_voz_generar($cfg, $texto) {
    $t = wa_voz_texto_limpio($texto);
    if ($t === '') return null;
    if (mb_strlen($t) > WA_VOZ_MAX) $t = mb_substr($t, 0, WA_VOZ_MAX);

    if (!empty($cfg['elevenKey'])) {
        $voz = !empty($cfg['elevenVoiceId']) ? $cfg['elevenVoiceId'] : 'EXAVITQu4vr4xnSDxMaL';   // Sarah, voz femenina
        $modelo = !empty($cfg['elevenModel']) ? $cfg['elevenModel'] : 'eleven_multilingual_v2';
        $ch = curl_init('https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($voz) . '?output_format=mp3_44100_128');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>90, CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['xi-api-key: ' . $cfg['elevenKey'], 'Content-Type: application/json', 'Accept: audio/mpeg'],
            CURLOPT_POSTFIELDS=>json_encode(['text'=>$t, 'model_id'=>$modelo,
                'voice_settings'=>['stability'=>0.45, 'similarity_boost'=>0.8, 'style'=>0.2, 'use_speaker_boost'=>true],
                'language_code'=>'es'], JSON_UNESCAPED_UNICODE)]);
        $b = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code >= 200 && $code < 300 && is_string($b) && strlen($b) > 1000) return ['bytes'=>$b, 'mime'=>'audio/mpeg', 'voz'=>'elevenlabs'];
        error_log('wa-voz: elevenlabs ' . $code . ' ' . substr((string)$b, 0, 200));
    }
    if (empty($cfg['openaiKey'])) return null;
    $ch = curl_init('https://api.openai.com/v1/audio/speech');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>90, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $cfg['openaiKey'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>'gpt-4o-mini-tts', 'voice'=>'shimmer', 'input'=>$t,
            'instructions'=>'Habla en español de Chile, como una recepcionista de spa: cálida, cercana y tranquila.',
            'response_format'=>'mp3'], JSON_UNESCAPED_UNICODE)]);
    $b = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code >= 200 && $code < 300 && is_string($b) && strlen($b) > 1000) return ['bytes'=>$b, 'mime'=>'audio/mpeg', 'voz'=>'openai'];
    error_log('wa-voz: openai tts ' . $code . ' ' . substr((string)$b, 0, 200));
    return null;
}

/* ── Enviarlo por WhatsApp: primero se sube el archivo, después se manda ── */
function wa_enviar_audio($cfg, $to, $bytes, $mime = 'audio/mpeg') {
    if (empty($cfg['waToken']) || empty($cfg['waPhoneId'])) return false;
    $tmp = tempnam(sys_get_temp_dir(), 'voz') . '.mp3';
    if (@file_put_contents($tmp, $bytes) === false) return false;
    $ch = curl_init('https://graph.facebook.com/v23.0/' . $cfg['waPhoneId'] . '/media');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>60, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $cfg['waToken']],
        CURLOPT_POSTFIELDS=>['messaging_product'=>'whatsapp', 'type'=>$mime,
                             'file'=>new CURLFile($tmp, $mime, basename($tmp))]]);
    $r = json_decode((string)curl_exec($ch), true); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    @unlink($tmp);
    if ($code < 200 || $code >= 300 || empty($r['id'])) { error_log('wa-voz: subida ' . $code . ' ' . json_encode($r)); return false; }

    $ch = curl_init('https://graph.facebook.com/v23.0/' . $cfg['waPhoneId'] . '/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $cfg['waToken'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['messaging_product'=>'whatsapp', 'to'=>$to, 'type'=>'audio',
                                         'audio'=>['id'=>$r['id']]])]);
    $r2 = curl_exec($ch); $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code2 < 200 || $code2 >= 300) { error_log('wa-voz: envio ' . $code2 . ' ' . substr((string)$r2, 0, 200)); return false; }
    return true;
}
