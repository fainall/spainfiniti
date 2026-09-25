<?php
/**
 * Enrutador para probar el sitio en el computador con el servidor de PHP:
 *
 *   php -S localhost:8080 -t . dev/router.php
 *
 * Imita las reglas de .htaccess que usa Hostinger (direcciones sin .html,
 * /servicio/<id>, /categoria/<id>, /promocion) para que el sitio local se
 * comporte igual que el real. Solo se usa en desarrollo (en Hostinger está bloqueado).
 */
/* en Hostinger este archivo no hace nada */
if (php_sapi_name() !== 'cli-server') { http_response_code(404); exit; }

$ruta = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$raiz = realpath(__DIR__ . '/..');

/* nada fuera del proyecto, ni la carpeta de desarrollo ni archivos ocultos */
if (preg_match('#(^|/)\.|^/(dev|supabase|node_modules)(/|$)#', $ruta)) { http_response_code(404); exit('No encontrado'); }

$servir = function ($archivo, $query = []) use ($raiz) {
    foreach ($query as $k => $v) $_GET[$k] = $v;
    $_SERVER['SCRIPT_NAME'] = '/' . $archivo;
    if (substr($archivo, -4) === '.php') { chdir(dirname("$raiz/$archivo")); require "$raiz/$archivo"; return true; }
    header('Content-Type: text/html; charset=utf-8');
    readfile("$raiz/$archivo");
    return true;
};

if (preg_match('#^/servicio/([a-z0-9-]+)/?$#i', $ruta, $m)) return $servir('servicio.html', ['id' => $m[1]]);
if (preg_match('#^/categoria/([a-z0-9-]+)/?$#i', $ruta, $m)) return $servir('categoria.html', ['cat' => $m[1]]);
if (preg_match('#^/promocion([-/].*)?$#i', $ruta)) return $servir('promocion.html');

/* un archivo que existe lo entrega el servidor de PHP tal cual */
if ($ruta !== '/' && is_file($raiz . $ruta)) return false;

/* /agenda → agenda.html, / → index.html */
$sin = rtrim($ruta, '/');
if ($sin === '') return $servir('index.html');
if (is_file($raiz . $sin . '.html')) return $servir(ltrim($sin, '/') . '.html');

http_response_code(404);
echo 'No encontrado';
