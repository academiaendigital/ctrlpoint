<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion();
$uid = (int) $u['id'];

$id = ent('id', $_GET);
$v = q('SELECT * FROM ventas WHERE id = ?', [$id])->fetch();
if (!$v) { http_response_code(404); exit('Venta no encontrada.'); }

$errores = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $accion = txt('accion', 20);
    $pagoId = ent('pago_id');
    // El pago debe pertenecer a esta venta
    if ($pagoId && !q('SELECT 1 FROM pagos WHERE id = ? AND venta_id = ?', [$pagoId, $id])->fetchColumn()) $accion = '';
    $puedeCobrar = in_array($u['rol'], ['admin', 'ventas'], true);
    try {
        switch ($accion) {
            case 'recibido':
                if (!$puedeCobrar) throw new ErrorVenta('Sin permiso.');
                $ref = preg_replace('/[^A-Za-z0-9-]/', '', txt('referencia', 40));
                if ($ref === '') throw new ErrorVenta('Escribe la referencia del pago.');
                pago_recibido($pagoId, $ref, dec('monto'), uno_de(txt('moneda', 4), ['USD', 'USDT', 'VES', 'EUR'], 'USD'), $uid);
                flash('ok', 'Pago marcado como recibido. Verifica en el banco y confírmalo.');
                break;
            case 'confirmar':
                if ($u['rol'] !== 'admin') throw new ErrorVenta('Solo un admin confirma pagos.');
                confirmar_pago($pagoId, $uid);
                flash('ok', 'Pago confirmado. Stock descontado y tasa BCV fijada.');
                break;
            case 'rechazar':
                if ($u['rol'] !== 'admin') throw new ErrorVenta('Solo un admin rechaza pagos.');
                rechazar_pago($pagoId, $uid);
                flash('info', 'Pago rechazado. Puedes registrar un nuevo intento de pago.');
                break;
            case 'nuevo_pago':
                if (!$puedeCobrar) throw new ErrorVenta('Sin permiso.');
                nuevo_pago($id, uno_de(txt('metodo', 20), METODOS_PAGO, 'Pago Móvil'), $uid);
                break;
            case 'avanzar':
                flash('ok', 'Estado: ' . avanzar_estado($id, $uid));
                break;
            case 'cancelar':
                if ($u['rol'] !== 'admin') throw new ErrorVenta('Solo un admin cancela ventas.');
                if (!isset($_POST['seguro'])) throw new ErrorVenta('Marca la casilla para confirmar la cancelación.');
                cancelar_venta($id, $uid);
                flash('info', 'Venta cancelada.');
                break;
            case 'guia':
                $g = [
                    'empresa' => uno_de(txt('empresa', 10), EMPRESAS_ENVIO, 'Zoom'),
                    'dest_nombre' => txt('dest_nombre', 80),
                    'dest_cedula' => preg_replace('/[^VEJGPvejgp0-9-]/', '', txt('dest_cedula', 15)),
                    'dest_telefono' => preg_replace('/[^0-9+ -]/', '', txt('dest_telefono', 20)),
                    'dest_estado' => uno_de(txt('dest_estado', 30), ESTADOS_VE, ''),
                    'dest_ciudad' => txt('dest_ciudad', 60),
                    'dest_direccion' => txt('dest_direccion', 200),
                    'modalidad_pago' => uno_de(txt('modalidad_pago', 20), ['Cobro a destino', 'Pagado en origen'], 'Cobro a destino'),
                    'bultos' => max(1, min(20, ent('bultos'))),
                    'peso_kg' => dec('peso_kg'),
                ];
                if ($g['dest_nombre'] === '' || $g['dest_estado'] === '' || $g['dest_ciudad'] === '') throw new ErrorVenta('Destinatario, estado y ciudad son obligatorios.');
                $gid = crear_guia($id, $g, $uid);
                flash('ok', 'Guía generada. Ya puedes imprimir el rótulo.');
                break;
            case 'rastreo':
                $r = preg_replace('/[^A-Za-z0-9-]/', '', txt('rastreo', 40));
                q('UPDATE guias SET rastreo_empresa = ? WHERE venta_id = ?', [$r ?: null, $id]);
                bitacora("{$v['codigo']}: rastreo oficial $r", $uid);
                flash('ok', 'Número de rastreo guardado.');
                break;
            default:
                throw new ErrorVenta('Acción no válida.');
        }
        ir('venta.php?id=' . $id);
    } catch (ErrorVenta $ex) {
        $errores[] = $ex->getMessage();
    }
    $v = q('SELECT * FROM ventas WHERE id = ?', [$id])->fetch();
}

$items = q('SELECT i.*, p.sku, p.nombre FROM venta_items i JOIN productos p ON p.id = i.producto_id WHERE i.venta_id = ?', [$id])->fetchAll();
$pagos = q('SELECT p.*, u.nombre AS confirmador FROM pagos p LEFT JOIN usuarios u ON u.id = p.confirmado_por WHERE p.venta_id = ? ORDER BY p.id', [$id])->fetchAll();
$guia = q('SELECT * FROM guias WHERE venta_id = ?', [$id])->fetch();
$tasa = tasa_vigente();
$costo = array_sum(array_map(fn($i) => $i['cantidad'] * $i['costo_unit_usd'], $items));
$abierto = in_array($v['estado'], ['Nuevo', 'Esperando pago'], true);
$hayPagoAbierto = (bool) array_filter($pagos, fn($p) => in_array($p['estado'], ['Pendiente', 'Recibido'], true));
$totalBs = $v['total_bs'] ?? ($tasa ? a_bs((float) $v['total_usd'], (float) $tasa['usd_bs']) : null);

cabecera($v['codigo'], $u, 'ventas.php');
?>
<p><a href="ventas.php">← Ventas</a></p>
<div class="titulo"><h1><?= e($v['codigo']) ?></h1><?= insignia($v['estado']) ?></div>
<?php foreach ($errores as $err): ?><p class="flash bad"><?= e($err) ?></p><?php endforeach; ?>

<div class="grid2">
  <div class="panel">
    <h2>Pedido</h2>
    <p><strong><?= e($v['cliente']) ?></strong> <?= $v['telefono'] ? '· ' . e($v['telefono']) : '' ?><br>
      <span class="hint">Creado <?= e(date('d/m/Y H:i', strtotime($v['creado']))) ?></span></p>
    <table class="mini"><thead><tr><th>Producto</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Subtotal</th></tr></thead><tbody>
      <?php foreach ($items as $i): ?><tr><td><?= e($i['nombre']) ?> <span class="hint mono"><?= e($i['sku']) ?></span></td><td class="num"><?= (int) $i['cantidad'] ?></td><td class="num"><?= usd($i['precio_unit_usd']) ?></td><td class="num"><?= usd($i['cantidad'] * $i['precio_unit_usd']) ?></td></tr><?php endforeach; ?>
    </tbody><tfoot>
      <tr><th colspan="3">Total</th><th class="num"><?= usd($v['total_usd']) ?></th></tr>
      <tr><td colspan="3"><?= $v['tasa_usd_bs'] ? 'En Bs (tasa fijada ' . e(number_format((float) $v['tasa_usd_bs'], 2, ',', '.')) . ')' : 'En Bs (tasa de hoy)' ?></td><td class="num"><?= $totalBs !== null ? bs($totalBs) : '—' ?></td></tr>
      <tr><td colspan="3" class="hint">Ganancia</td><td class="num hint"><?= usd((float) $v['total_usd'] - $costo) ?></td></tr>
    </tfoot></table>
    <p>Entrega: <strong><?= e(['retiro' => 'Retiro en tienda', 'delivery' => 'Delivery local', 'nacional' => 'Envío nacional'][$v['envio_tipo']]) ?></strong>
      <?= $v['envio_tipo'] === 'nacional' ? '· ' . e($v['envio_empresa']) . ' · ' . e($v['envio_ciudad']) : '' ?>
      <?= $v['envio_tipo'] === 'delivery' ? '· ' . e($v['envio_direccion']) : '' ?></p>
    <?php if ($v['nota']): ?><p class="hint">Nota: <?= e($v['nota']) ?></p><?php endif; ?>

    <?php if (isset(SIGUIENTE_ESTADO[$v['estado']])): ?>
      <form method="post" action="venta.php?id=<?= $id ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="accion" value="avanzar">
        <button class="btn" type="submit">Marcar como «<?= e(SIGUIENTE_ESTADO[$v['estado']]) ?>»</button></form>
    <?php endif; ?>
    <?php if ($u['rol'] === 'admin' && !in_array($v['estado'], ['Cancelado', 'Entregado'], true)): ?>
      <details class="peligro"><summary>Cancelar venta</summary>
        <form method="post" action="venta.php?id=<?= $id ?>"><?= csrf_field() ?><input type="hidden" name="accion" value="cancelar">
          <label><input type="checkbox" name="seguro" value="1" required> Sí, cancelar <?= in_array($v['estado'], ESTADOS_CON_STOCK_DESCONTADO, true) ? 'y devolver el stock' : '' ?></label>
          <button class="btn mal" type="submit">Cancelar venta</button></form>
      </details>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Pagos</h2>
    <p class="hint">Pendiente → Recibido (con referencia) → Confirmado o Rechazado. Los datos de cobro se envían por WhatsApp; nunca se publican en la web.</p>
    <?php foreach ($pagos as $p): ?>
      <div class="pago">
        <p><?= insignia($p['estado']) ?> <strong><?= e($p['metodo']) ?></strong>
          <?= $p['referencia'] ? '· ref. <span class="mono">' . e($p['referencia']) . '</span>' : '' ?>
          <?= $p['monto'] !== null ? '· ' . e(number_format((float) $p['monto'], 2, ',', '.')) . ' ' . e($p['moneda']) : '' ?>
          <?= $p['confirmador'] ? '<br><span class="hint">por ' . e($p['confirmador']) . ' · ' . e(date('d/m/y H:i', strtotime($p['confirmado_en']))) . '</span>' : '' ?></p>
        <?php if ($p['estado'] === 'Pendiente' && $abierto): ?>
          <form method="post" action="venta.php?id=<?= $id ?>" class="row"><?= csrf_field() ?><input type="hidden" name="accion" value="recibido"><input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>">
            <div><label for="ref<?= (int) $p['id'] ?>">Referencia</label><input id="ref<?= (int) $p['id'] ?>" name="referencia" required maxlength="40"></div>
            <div><label for="monto<?= (int) $p['id'] ?>">Monto</label><input id="monto<?= (int) $p['id'] ?>" name="monto" inputmode="decimal" value="<?= e($p['moneda'] === 'VES' && $totalBs !== null ? number_format($totalBs, 2, '.', '') : $v['total_usd']) ?>"></div>
            <div class="chico"><label for="mon<?= (int) $p['id'] ?>">Moneda</label><select id="mon<?= (int) $p['id'] ?>" name="moneda"><?= opciones(['USD', 'USDT', 'VES', 'EUR'], $p['moneda']) ?></select></div>
            <div><button class="btn" type="submit">Marcar recibido</button></div>
          </form>
        <?php endif; ?>
        <?php if ($u['rol'] === 'admin' && in_array($p['estado'], ['Pendiente', 'Recibido'], true) && $abierto): ?>
          <div class="acciones">
            <?php if ($p['estado'] === 'Recibido'): ?>
            <form method="post" action="venta.php?id=<?= $id ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="accion" value="confirmar"><input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>"><button class="btn" type="submit">Confirmar pago</button></form>
            <?php endif; ?>
            <form method="post" action="venta.php?id=<?= $id ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="accion" value="rechazar"><input type="hidden" name="pago_id" value="<?= (int) $p['id'] ?>"><button class="btn sec" type="submit">Rechazar</button></form>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($abierto && !$hayPagoAbierto): ?>
      <form method="post" action="venta.php?id=<?= $id ?>" class="row"><?= csrf_field() ?><input type="hidden" name="accion" value="nuevo_pago">
        <div><label for="metodo">Nuevo intento de pago</label><select id="metodo" name="metodo"><?= opciones(METODOS_PAGO, 'Pago Móvil') ?></select></div>
        <div><button class="btn sec" type="submit">Agregar</button></div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($v['envio_tipo'] === 'nacional'): ?>
<div class="panel" id="guia">
  <h2>Guía de envío</h2>
  <?php if ($guia): ?>
    <p>Guía interna <strong class="mono"><?= e($guia['numero']) ?></strong> · <?= e($guia['empresa']) ?> · <?= e($guia['dest_ciudad']) ?>, <?= e($guia['dest_estado']) ?> · <?= e($guia['modalidad_pago']) ?></p>
    <p><a class="btn" href="guia.php?id=<?= (int) $guia['id'] ?>" target="_blank" rel="noopener">Imprimir rótulo con QR</a></p>
    <form method="post" action="venta.php?id=<?= $id ?>" class="row"><?= csrf_field() ?><input type="hidden" name="accion" value="rastreo">
      <div><label for="rastreo">N.º de rastreo oficial de <?= e($guia['empresa']) ?></label><input id="rastreo" name="rastreo" maxlength="40" value="<?= e($guia['rastreo_empresa']) ?>"></div>
      <div><button class="btn sec" type="submit">Guardar</button></div>
    </form>
  <?php elseif (!in_array($v['estado'], ['Pago verificado', 'Preparado'], true)): ?>
    <p class="hint">La guía se genera cuando el pago esté confirmado.</p>
  <?php else: ?>
    <form method="post" action="venta.php?id=<?= $id ?>"><?= csrf_field() ?><input type="hidden" name="accion" value="guia">
      <div class="row">
        <div><label for="empresa">Empresa</label><select id="empresa" name="empresa"><?= opciones(EMPRESAS_ENVIO, $v['envio_empresa']) ?></select></div>
        <div><label for="dest_nombre">Destinatario</label><input id="dest_nombre" name="dest_nombre" required maxlength="80" value="<?= e($v['cliente']) ?>"></div>
        <div><label for="dest_cedula">Cédula</label><input id="dest_cedula" name="dest_cedula" maxlength="15" placeholder="V-12345678"></div>
        <div><label for="dest_telefono">Teléfono</label><input id="dest_telefono" name="dest_telefono" maxlength="20" value="<?= e($v['telefono']) ?>"></div>
      </div>
      <div class="row">
        <div><label for="dest_estado">Estado</label><select id="dest_estado" name="dest_estado" required><option value="">—</option><?= opciones(ESTADOS_VE, null) ?></select></div>
        <div><label for="dest_ciudad">Ciudad</label><input id="dest_ciudad" name="dest_ciudad" required maxlength="60" value="<?= e($v['envio_ciudad']) ?>"></div>
        <div class="ancho2"><label for="dest_direccion">Oficina de la empresa o dirección</label><input id="dest_direccion" name="dest_direccion" maxlength="200"></div>
      </div>
      <div class="row">
        <div><label for="modalidad_pago">Flete</label><select id="modalidad_pago" name="modalidad_pago"><?= opciones(['Cobro a destino', 'Pagado en origen'], 'Cobro a destino') ?></select></div>
        <div class="chico"><label for="bultos">Bultos</label><input id="bultos" name="bultos" type="number" min="1" max="20" value="1"></div>
        <div class="chico"><label for="peso_kg">Peso kg</label><input id="peso_kg" name="peso_kg" inputmode="decimal"></div>
        <div><button class="btn" type="submit">Generar guía CP-G</button></div>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php pie($u);
