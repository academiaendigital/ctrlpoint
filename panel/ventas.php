<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion();

$estados = ['Nuevo', 'Esperando pago', 'Pago verificado', 'Preparado', 'Despachado', 'Entregado', 'Cancelado'];
$estado = is_string($_GET['estado'] ?? null) && in_array($_GET['estado'], $estados, true) ? $_GET['estado'] : '';
$busca = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';

$sql = "SELECT v.*, (SELECT p.estado FROM pagos p WHERE p.venta_id = v.id ORDER BY p.id DESC LIMIT 1) AS pago_estado,
               (SELECT g.numero FROM guias g WHERE g.venta_id = v.id LIMIT 1) AS guia
        FROM ventas v WHERE 1=1";
$par = [];
if ($estado !== '') { $sql .= ' AND v.estado = ?'; $par[] = $estado; }
if ($busca !== '') { $sql .= ' AND (v.codigo LIKE ? OR v.cliente LIKE ? OR v.telefono LIKE ?)'; $like = '%' . addcslashes($busca, '%_\\') . '%'; array_push($par, $like, $like, $like); }
$sql .= ' ORDER BY v.id DESC LIMIT 200';
$ventas = q($sql, $par)->fetchAll();

cabecera('Ventas', $u, 'ventas.php');
?>
<div class="titulo">
  <h1>Ventas</h1>
  <div class="acciones"><a class="btn" href="venta_nueva.php">+ Nueva venta</a></div>
</div>
<form class="panel row" method="get" action="ventas.php">
  <div><label for="q">Buscar</label><input id="q" name="q" value="<?= e($busca) ?>" placeholder="Código, cliente o teléfono"></div>
  <div><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option><?= opciones($estados, $estado) ?></select></div>
  <div><button class="btn sec" type="submit">Filtrar</button></div>
</form>
<div class="panel scroll">
  <table>
    <thead><tr><th>Código</th><th>Fecha</th><th>Cliente</th><th class="num">Total</th><th>Pago</th><th>Estado</th><th>Envío</th></tr></thead>
    <tbody>
    <?php foreach ($ventas as $v): ?>
      <tr>
        <td class="mono"><a href="venta.php?id=<?= (int) $v['id'] ?>"><?= e($v['codigo']) ?></a></td>
        <td><?= e(date('d/m/y', strtotime($v['creado']))) ?></td>
        <td><?= e($v['cliente']) ?></td>
        <td class="num"><?= usd($v['total_usd']) ?></td>
        <td><?= $v['pago_estado'] ? insignia($v['pago_estado']) : '—' ?></td>
        <td><?= insignia($v['estado']) ?></td>
        <td><?= e(['retiro' => 'Retiro', 'delivery' => 'Delivery', 'nacional' => 'Nacional'][$v['envio_tipo']]) ?>
          <?= $v['envio_tipo'] === 'nacional' ? '· ' . e($v['envio_empresa']) . ' · ' . e($v['envio_ciudad']) . ($v['guia'] ? ' <span class="badge ok">' . e($v['guia']) . '</span>' : '') : '' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$ventas): ?><tr><td colspan="7" class="hint">No hay ventas con ese filtro.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php pie($u);
