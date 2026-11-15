<?php
declare(strict_types=1);

function cabecera(string $titulo, ?array $u = null, string $activo = ''): void
{
    $menu = ['index.php' => 'Inicio', 'productos.php' => 'Inventario', 'ventas.php' => 'Ventas', 'ajustes.php' => 'Precios y tasa', 'usuarios.php' => 'Usuarios'];
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo) ?> · Panel CTRL+</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/panel.css?v=1">
<script src="assets/panel.js?v=1" defer></script>
</head>
<body>
<?php if ($u): ?>
<div class="app">
  <nav class="side" aria-label="Menú del panel">
    <a class="brand" href="index.php"><img src="assets/logo.svg" alt="CTRL+" width="96" height="32"> <span>Panel</span></a>
    <?php foreach ($menu as $href => $nombre): ?>
      <a class="tab<?= $activo === $href ? ' on' : '' ?>" href="<?= $href ?>"<?= $activo === $href ? ' aria-current="page"' : '' ?>><?= e($nombre) ?></a>
    <?php endforeach; ?>
    <div class="who">
      <strong><?= e($u['nombre']) ?></strong><?= e($u['rol']) ?>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="link" type="submit">Cerrar sesión</button></form>
    </div>
  </nav>
  <main><section>
<?php else: ?>
<main class="solo"><section>
<?php endif;
    if (!empty($_SESSION['flash'])) {
        [$tipo, $msg] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<p class="flash ' . e($tipo) . '" role="status">' . e($msg) . '</p>';
    }
}

function pie(?array $u = null): void
{
    echo $u ? "</section></main>\n</div>\n" : "</section></main>\n";
    echo "</body>\n</html>\n";
}

function opciones(array $lista, ?string $sel): string
{
    $h = '';
    foreach ($lista as $valor => $texto) {
        if (is_int($valor)) $valor = $texto;
        $h .= '<option value="' . e($valor) . '"' . ((string) $valor === (string) $sel ? ' selected' : '') . '>' . e($texto) . '</option>';
    }
    return $h;
}

function insignia(string $estado): string
{
    $clase = match ($estado) {
        'Confirmado', 'Pago verificado', 'Entregado' => 'ok',
        'Recibido', 'Preparado', 'Despachado' => 'info',
        'Rechazado', 'Cancelado' => 'bad',
        default => 'warn',
    };
    return '<span class="badge ' . $clase . '">' . e($estado) . '</span>';
}

const ESTADOS_VE = ['Amazonas', 'Anzoátegui', 'Apure', 'Aragua', 'Barinas', 'Bolívar', 'Carabobo', 'Cojedes', 'Delta Amacuro',
    'Distrito Capital', 'Falcón', 'Guárico', 'La Guaira', 'Lara', 'Mérida', 'Miranda', 'Monagas', 'Nueva Esparta', 'Portuguesa',
    'Sucre', 'Táchira', 'Trujillo', 'Yaracuy', 'Zulia'];
const EMPRESAS_ENVIO = ['Zoom', 'MRW', 'Tealca'];
const METODOS_PAGO = ['Pago Móvil', 'Binance', 'PayPal', 'Transferencia', 'Efectivo'];
