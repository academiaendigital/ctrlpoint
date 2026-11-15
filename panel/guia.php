<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion();

$g = q('SELECT g.*, v.codigo, v.creado AS fecha_venta, (SELECT SUM(cantidad) FROM venta_items WHERE venta_id = v.id) AS unidades
        FROM guias g JOIN ventas v ON v.id = g.venta_id WHERE g.id = ?', [ent('id', $_GET)])->fetch();
if (!$g) { http_response_code(404); exit('Guía no encontrada.'); }

// El QR solo lleva códigos internos, sin datos personales del cliente
$qr = implode('|', ['CTRL+', $g['numero'], $g['codigo'], $g['empresa'], $g['rastreo_empresa'] ?? '']);
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($g['numero']) ?> · Rótulo CTRL+</title>
<link rel="stylesheet" href="assets/guia.css?v=1">
<script src="assets/qrcode.js" defer></script>
<script src="assets/panel.js?v=1" defer></script>
</head>
<body>
<p class="no-print"><button type="button" id="imprimir">Imprimir</button> <a href="venta.php?id=<?= (int) $g['venta_id'] ?>#guia">Volver a la venta</a></p>
<article class="rotulo">
  <header>
    <img src="assets/logo.svg" alt="CTRL+" width="120" height="40">
    <div class="num"><small>Guía interna</small><strong><?= e($g['numero']) ?></strong><small>Pedido <?= e($g['codigo']) ?></small></div>
  </header>
  <section class="empresa"><?= e(mb_strtoupper($g['empresa'])) ?> · <?= e($g['modalidad_pago']) ?></section>
  <section class="bloque">
    <h2>Destinatario</h2>
    <p class="grande"><?= e($g['dest_nombre']) ?></p>
    <p><?= $g['dest_cedula'] ? 'C.I. ' . e($g['dest_cedula']) . ' · ' : '' ?><?= e($g['dest_telefono']) ?></p>
    <p class="grande"><?= e($g['dest_ciudad']) ?>, <?= e($g['dest_estado']) ?></p>
    <?php if ($g['dest_direccion']): ?><p><?= e($g['dest_direccion']) ?></p><?php endif; ?>
  </section>
  <section class="bloque">
    <h2>Remitente</h2>
    <p>CTRL+ · San Juan de los Morros, Guárico · 0412-778-2492</p>
  </section>
  <footer>
    <div class="qr" data-qr="<?= e($qr) ?>" role="img" aria-label="Código QR de la guía <?= e($g['numero']) ?>"></div>
    <dl>
      <dt>Fecha</dt><dd><?= e(date('d/m/Y', strtotime($g['creado']))) ?></dd>
      <dt>Bultos</dt><dd><?= (int) $g['bultos'] ?></dd>
      <dt>Peso</dt><dd><?= $g['peso_kg'] !== null ? e(number_format((float) $g['peso_kg'], 2, ',', '.')) . ' kg' : '—' ?></dd>
      <dt>Artículos</dt><dd><?= (int) $g['unidades'] ?></dd>
      <?php if ($g['rastreo_empresa']): ?><dt>Rastreo</dt><dd class="mono"><?= e($g['rastreo_empresa']) ?></dd><?php endif; ?>
    </dl>
  </footer>
</article>
</body>
</html>
