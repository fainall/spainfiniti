<?php
/**
 * Envío de campaña de Email Marketing — Spa Infinity
 * POST JSON { key, subject, imageUrl?, message, btnText?, btnUrl?, clientIds:[...] }
 * Busca los emails de esos clientes en Supabase, descuenta suprimidos y envía.
 */
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }
@set_time_limit(120);

$cfg = file_exists(__DIR__.'/bot-config.php') ? require __DIR__.'/bot-config.php' : [];
$secret = $cfg['campaignKey'] ?? 'spa-camp-2026';

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (($in['key'] ?? '') !== $secret) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$subject = trim($in['subject'] ?? '');
$message = trim($in['message'] ?? '');
$imageUrl= trim($in['imageUrl'] ?? '');
$btnText = trim($in['btnText'] ?? '');
$btnUrl  = trim($in['btnUrl'] ?? '');
$ids     = $in['clientIds'] ?? [];
if (!$subject || !$message) { http_response_code(400); echo json_encode(['error'=>'Falta asunto o mensaje']); exit; }
if (!is_array($ids) || !count($ids)) { echo json_encode(['sent'=>0,'skipped'=>0,'note'=>'sin destinatarios']); exit; }
$ids = array_slice($ids, 0, 300); // tope de seguridad

$SUPA='https://bxwamppamqxtncvfdycy.supabase.co/rest/v1/';
$KEY='eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ4d2FtcHBhbXF4dG5jdmZkeWN5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzgzMzg1MjAsImV4cCI6MjA5MzkxNDUyMH0.UiSFfFCU8GusDWqfgf3c9PL10ctHwZtaWvHFY8VghzA';
function supa($m,$p,$b=null){ global $SUPA,$KEY; $ch=curl_init($SUPA.$p); $h=['apikey: '.$KEY,'Authorization: Bearer '.$KEY,'Content-Type: application/json']; if($m!=='GET')$h[]='Prefer: return=representation'; curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>20]); if($b!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($b)); $r=curl_exec($ch); curl_close($ch); return json_decode($r,true); }

$idList = implode(',', array_map(fn($x)=>'"'.$x.'"', $ids));
$clients = supa('GET','clients?select=id,name,email&id=in.('.$idList.')') ?: [];
$supp = supa('GET','email_suppressions?select=email') ?: [];
$suppSet = [];
foreach ($supp as $s) $suppSet[strtolower($s['email'])] = true;

$base = $cfg['bookingBase'] ?? 'https://spainfinity.cl';
$sent=0; $skipped=0;
foreach ($clients as $c) {
    $email = trim($c['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($suppSet[strtolower($email)])) { $skipped++; continue; }
    $unsub = $base.'/api/unsubscribe.php?e='.urlencode($email);
    $html  = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#333">';
    $html .= '<div style="background:linear-gradient(135deg,#c5a467,#8a7344);color:#fff;padding:22px;text-align:center;border-radius:8px 8px 0 0"><h2 style="margin:0;font-family:Georgia,serif">'.htmlspecialchars($cfg['businessName']??'Spa Infinity').'</h2></div>';
    $html .= '<div style="background:#fff;border:1px solid #e0e0e0;border-top:none;padding:24px;border-radius:0 0 8px 8px">';
    if ($imageUrl) $html .= '<img src="'.htmlspecialchars($imageUrl).'" alt="" style="max-width:100%;border-radius:8px;margin-bottom:16px">';
    $html .= '<div style="font-size:15px;line-height:1.7">'.nl2br(htmlspecialchars($message)).'</div>';
    if ($btnText && $btnUrl) $html .= '<div style="text-align:center;margin-top:22px"><a href="'.htmlspecialchars($btnUrl).'" style="display:inline-block;background:#c5a467;color:#fff;text-decoration:none;padding:13px 30px;border-radius:30px;font-weight:bold">'.htmlspecialchars($btnText).'</a></div>';
    $html .= '<div style="margin-top:24px;padding-top:14px;border-top:1px solid #eee;font-size:11px;color:#999;text-align:center">'.htmlspecialchars($cfg['address']??'').'<br><a href="'.$unsub.'" style="color:#999">Cancelar suscripción</a></div>';
    $html .= '</div></div>';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: ".($cfg['businessName']??'Spa Infinity')." <noreply@spainfinity.cl>\r\nReply-To: reservainfinity@spainfinity.cl\r\nList-Unsubscribe: <".$unsub.">\r\n";
    if (@mail($email, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, $headers)) $sent++; else $skipped++;
    usleep(80000); // 80ms entre envíos
}
supa('POST','campaigns',['subject'=>$subject,'sent_count'=>$sent]);
echo json_encode(['sent'=>$sent,'skipped'=>$skipped]);
