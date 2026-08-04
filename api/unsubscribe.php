<?php
/** Desuscripción de emails — Spa Infinity. Agrega el email a email_suppressions. */
$email = trim($_GET['e'] ?? '');
$ok = false;
if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $SUPA='https://bxwamppamqxtncvfdycy.supabase.co/rest/v1/';
    $KEY='eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ4d2FtcHBhbXF4dG5jdmZkeWN5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzgzMzg1MjAsImV4cCI6MjA5MzkxNDUyMH0.UiSFfFCU8GusDWqfgf3c9PL10ctHwZtaWvHFY8VghzA';
    $ch=curl_init($SUPA.'email_suppressions');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['apikey: '.$KEY,'Authorization: Bearer '.$KEY,'Content-Type: application/json','Prefer: resolution=ignore-duplicates'],CURLOPT_POSTFIELDS=>json_encode(['email'=>$email]),CURLOPT_TIMEOUT=>15]);
    curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    $ok = ($code>=200 && $code<300);
}
header('Content-Type: text/html; charset=UTF-8');
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Suscripción cancelada</title></head><body style="font-family:Arial,sans-serif;background:#f5f2ec;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="background:#fff;padding:40px;border-radius:14px;text-align:center;max-width:420px;box-shadow:0 10px 40px rgba(0,0,0,.1)"><h2 style="color:#8a7344;font-family:Georgia,serif">Spa Infinity</h2>'.($ok ? '<p style="color:#333">Listo ✅ No recibirás más correos de marketing.</p>' : '<p style="color:#333">No pudimos procesar la solicitud. Escríbenos y te ayudamos.</p>').'</div></body></html>';
