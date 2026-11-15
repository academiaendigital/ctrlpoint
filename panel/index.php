<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion();

$tasa = tasa_vigente();
$mes = q("SELECT COUNT(DISTINCT v.id) AS n, COALESCE(SUM(i.cantidad * i.precio_unit_usd), 0) AS ventas,
                 COALESCE(SUM(i.cantidad * (i.precio_unit_usd - i.costo_unit_usd)), 0) AS ganancia
          FROM ventas v JOIN venta_items i ON i.venta_id = v.id
          WHERE v.estado IN ('Pago verificado','Preparado','Despachado','Entregado')
            AND v.creado >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetch();
$porConfirmar = q("SELECT v.id, v.codigo, v.cliente, p.metodo, p.referencia, p.monto, p.moneda
                   FROM pagos p JOIN ventas v ON v.id = p.venta_id WHERE p.estado = 'Recibido' ORDER BY p.creado")->fetchAll();
$sinGuia = q("SELECT v.id, v.codigo, v.cliente, v.envio_ciudad, v.envio_empresa FROM ventas v
              LEFT JOIN guias g ON g.venta_id = v.id
              WHERE v.envio_tipo = 'nacional' AND v.estado IN ('Pago verificado','Preparado') AND g.id IS NULL ORDER BY v.id")->fetchAll();
$stockBajo = q('SELECT id, sku, nombre, stock, stock_minimo FROM productos WHERE activo = 1 AND stock <= stock_minimo ORDER BY stock, nombre')->fetchAll();
$inventario = q('SELECT COALESCE(SUM(stock), 0) AS unidades, COALESCE(SUM(stock * costo_usd), 0) AS costo FROM productos WHERE activo = 1')->fetch();

cabecera('Inicio', $u, 'index.php');
?>
<h1>Inicio</h1>
<?php if (!$tasa): ?><p class="flash warn">Falta registrar la tasa BCV. <a href="ajustes.php">Regístrala aquí</a> para ver precios en Bs y confirmar pagos.</p><?php endif; ?>
<div class="kpis">
  <div class="kpi"><span>Ventas del mes</span><strong><?= usd($mes['ventas']) ?></strong><small><?= (int) $mes['n'] ?> pedidos con pago confirmado</small></div>
  <div class="kpi"><span>Ganancia del mes</span><strong><?= usd($mes['ganancia']) ?></strong><small>Precio − costo</small></div>
  <div class="kpi"><span>Inventario</span><strong><?= (int) $inventario['unidades'] ?> u.</strong><small>Costo <?= usd($inventario['costo']) ?></small></div>
  <div class="kpi"><span>Tasa BCV</span><strong><?= $tasa ? e(number_format((float) $tasa['usd_bs'], 2, ',', '.')) : '—' ?></strong><small><?= $tasa ? 'Bs/USD · ' . e(date('d/m/Y', strtotime($tasa['fecha']))) : 'Sin registrar' ?></small></div>
</div>

<div class="grid2">
  <div class="panel">
    <h2>Pagos por confirmar</h2>
    <?php if (!$porConfirmar): ?><p class="hint">Nada pendiente.</p><?php else: ?>
    <ul class="lista"><?php foreach ($porConfirmar as $p): ?>
      <li><a href="venta.php?id=<?= (int) $p['id'] ?>"><?= e($p['codigo']) ?></a> · <?= e($p['cliente']) ?> · <?= e($p['metodo']) ?> · ref. <?= e($p['referencia']) ?> <?= $p['monto'] !== null ? '· ' . e($p['monto']) . ' ' . e($p['moneda']) : '' ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
  <div class="panel">
    <h2>Envíos nacionales sin guía</h2>
    <?php if (!$sinGuia): ?><p class="hint">Nada pendiente.</p><?php else: ?>
    <ul class="lista"><?php foreach ($sinGuia as $v): ?>
      <li><a href="venta.php?id=<?= (int) $v['id'] ?>#guia"><?= e($v['codigo']) ?></a> · <?= e($v['cliente']) ?> · <?= e($v['envio_ciudad']) ?> · <?= e($v['envio_empresa']) ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
</div>

<div class="panel">
  <h2>Stock bajo</h2>
  <?php if (!$stockBajo): ?><p class="hint">Todo el inventario está por encima del mínimo.</p><?php else: ?>
  <div class="scroll"><table>
    <thead><tr><th>SKU</th><th>Producto</th><th class="num">Stock</th><th class="num">Mínimo</th></tr></thead>
    <tbody><?php foreach ($stockBajo as $p): ?>
      <tr><td class="mono"><?= e($p['sku']) ?></td><td><a href="producto.php?id=<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></a></td>
      <td class="num"><?= $p['stock'] == 0 ? '<span class="badge bad">0</span>' : (int) $p['stock'] ?></td><td class="num"><?= (int) $p['stock_minimo'] ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
<?php pie($u);
