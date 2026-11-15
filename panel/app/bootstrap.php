<?php
/**
 * CTRL+ · Panel de administración. Arranque común: configuración, cabeceras de
 * seguridad, sesión, base de datos (PDO con consultas preparadas), CSRF y utilidades.
 */
declare(strict_types=1);

// Configuración: primero fuera de public_html (recomendado), luego app/config.php
$cfg = null;
foreach ([dirname(__DIR__, 3) . '/ctrlplus-config.php', __DIR__ . '/config.php'] as $archivo) {
    if (is_file($archivo)) { $cfg = require $archivo; break; }
}
if (!is_array($cfg)) {
    http_response_code(500);
    exit('Falta la configuración del panel. Copia app/config.example.php como config.php y complétalo.');
}
define('CFG', $cfg);

date_default_timezone_set('America/Caracas');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

const MAX_INTENTOS = 5;          // intentos fallidos antes de bloquear
const MINUTOS_BLOQUEO = 15;
const MINUTOS_INACTIVIDAD = 30;  // cierre de sesión por inactividad
const CLAVE_MINIMA = 10;

$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
define('ES_HTTPS', $esHttps);

if (PHP_SAPI !== 'cli') {
    if (!empty(CFG['forzar_https']) && !ES_HTTPS) {
        header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    if (ES_HTTPS) header('Strict-Transport-Security: max-age=31536000');
    header_remove('X-Powered-By');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name(ES_HTTPS ? '__Host-ctrlp' : 'ctrlp');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => ES_HTTPS, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();

    // Cierre por inactividad
    if (isset($_SESSION['uid'], $_SESSION['visto']) && time() - $_SESSION['visto'] > MINUTOS_INACTIVIDAD * 60) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['flash'] = ['info', 'Tu sesión se cerró por inactividad.'];
    }
    $_SESSION['visto'] = time();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $d = CFG['db'];
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $d['host'], $d['port'] ?? 3306, $d['nombre']),
            $d['usuario'],
            $d['clave'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '-04:00'");
    }
    return $pdo;
}

/** Ejecuta una consulta preparada y devuelve el statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function e(null|string|int|float $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function usd(float|string|null $n): string
{
    return '$' . number_format((float) $n, 2, ',', '.');
}

function bs(float|string|null $n): string
{
    return 'Bs ' . number_format((float) $n, 2, ',', '.');
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Verifica token CSRF y origen en toda petición POST. */
function verificar_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $ok = is_string($_POST['csrf'] ?? null) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origen !== '' && parse_url($origen, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) $ok = false;
    if (!$ok) {
        http_response_code(400);
        exit('Solicitud no válida. Recarga la página e inténtalo de nuevo.');
    }
}

// ---------- Mensajes y redirecciones ----------
function flash(string $tipo, string $msg): void
{
    $_SESSION['flash'] = [$tipo, $msg];
}

/** Redirige solo a rutas internas del panel (evita redirecciones abiertas). */
function ir(string $ruta): never
{
    if (!preg_match('~^[a-z_]+\.php(\?[A-Za-z0-9_=&%-]*)?$~', $ruta)) $ruta = 'index.php';
    header('Location: ' . $ruta, true, 303);
    exit;
}

// ---------- Autenticación ----------
function usuario(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = q('SELECT id, nombre, correo, rol FROM usuarios WHERE id = ? AND activo = 1', [$_SESSION['uid']])->fetch() ?: null;
            if ($u === null) $_SESSION = [];
        }
    }
    return $u;
}

function requiere_sesion(string $rol = ''): array
{
    $u = usuario();
    if ($u === null) ir('login.php');
    if ($rol !== '' && $u['rol'] !== 'admin' && $u['rol'] !== $rol) {
        http_response_code(403);
        exit('No tienes permiso para esta sección.');
    }
    verificar_post();
    return $u;
}

function ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

function bitacora(string $accion, ?int $uid = null): void
{
    q('INSERT INTO bitacora (usuario_id, accion, ip) VALUES (?, ?, ?)', [$uid ?? ($_SESSION['uid'] ?? null), mb_substr($accion, 0, 255), ip()]);
}

// ---------- Entradas ----------
function txt(string $k, int $max = 255): string
{
    $v = $_POST[$k] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function ent(string $k, ?array $src = null): int
{
    $v = ($src ?? $_POST)[$k] ?? '';
    return is_string($v) && preg_match('/^-?\d{1,9}$/', trim($v)) ? (int) trim($v) : 0;
}

/** Decimal con coma o punto; null si vacío o inválido. */
function dec(string $k): ?float
{
    $v = $_POST[$k] ?? '';
    if (!is_string($v)) return null;
    $v = str_replace(',', '.', trim($v));
    return preg_match('/^-?\d{1,9}(\.\d{1,4})?$/', $v) ? (float) $v : null;
}

function uno_de(string $v, array $opciones, string $porDefecto): string
{
    return in_array($v, $opciones, true) ? $v : $porDefecto;
}

require __DIR__ . '/precios.php';
require __DIR__ . '/vista.php';
require __DIR__ . '/ventas.php';
