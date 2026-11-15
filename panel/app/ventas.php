<?php
/**
 * Lógica de ventas, pagos y guías. Todo cambio de stock ocurre dentro de una transacción
 * con bloqueo de filas (SELECT … FOR UPDATE) para evitar vender dos veces la misma unidad.
 */
declare(strict_types=1);

const ESTADOS_CON_STOCK_DESCONTADO = ['Pago verificado', 'Preparado', 'Despachado', 'Entregado'];
const SIGUIENTE_ESTADO = ['Pago verificado' => 'Preparado', 'Preparado' => 'Despachado', 'Despachado' => 'Entregado'];

final class ErrorVenta extends RuntimeException {}

/**
 * @param array $items lista de [producto_id, cantidad]
 * @return int id de la venta
 */
function crear_venta(array $d, array $items, string $metodoPago, int $uid): int
{
    if (!$items) throw new ErrorVenta('Agrega al menos un producto.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $renglones = [];
        $total = 0.0;
        foreach ($items as [$pid, $cant]) {
            $p = q('SELECT id, nombre, costo_usd, margen, stock, activo FROM productos WHERE id = ?', [$pid])->fetch();
            if (!$p) throw new ErrorVenta('Producto no encontrado.');
            if ($cant > (int) $p['stock']) throw new ErrorVenta("Stock insuficiente de {$p['nombre']} (hay {$p['stock']}).");
            $precio = precio_venta($p['costo_usd'], $p['margen']);
            $renglones[] = [$pid, $cant, $precio, (float) $p['costo_usd']];
            $total += $precio * $cant;
        }
        q("INSERT INTO ventas (codigo, cliente, telefono, estado, total_usd, envio_tipo, envio_ciudad, envio_direccion, envio_empresa, nota, creado_por)
           VALUES (?, ?, ?, 'Esperando pago', ?, ?, ?, ?, ?, ?, ?)",
            ['T' . bin2hex(random_bytes(5)), $d['cliente'], $d['telefono'] ?: null, round($total, 2), $d['envio_tipo'],
             $d['envio_ciudad'] ?: null, $d['envio_direccion'] ?: null, $d['envio_tipo'] === 'nacional' ? $d['envio_empresa'] : null, $d['nota'] ?: null, $uid]);
        $vid = (int) $pdo->lastInsertId();
        q('UPDATE ventas SET codigo = ? WHERE id = ?', [sprintf('CP-%04d', $vid), $vid]);
        foreach ($renglones as [$pid, $cant, $precio, $costo]) {
            q('INSERT INTO venta_items (venta_id, producto_id, cantidad, precio_unit_usd, costo_unit_usd) VALUES (?,?,?,?,?)', [$vid, $pid, $cant, $precio, $costo]);
        }
        q("INSERT INTO pagos (venta_id, metodo, moneda) VALUES (?, ?, ?)", [$vid, $metodoPago, moneda_de($metodoPago)]);
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    bitacora(sprintf('Creó venta CP-%04d por %s', $vid, usd($total)), $uid);
    return $vid;
}

function moneda_de(string $metodo): string
{
    return match ($metodo) { 'Pago Móvil', 'Transferencia' => 'VES', 'Binance' => 'USDT', default => 'USD' };
}

function pago_recibido(int $pagoId, string $referencia, ?float $monto, string $moneda, int $uid): void
{
    $n = q("UPDATE pagos SET estado = 'Recibido', referencia = ?, monto = ?, moneda = ? WHERE id = ? AND estado = 'Pendiente'",
        [$referencia, $monto, $moneda, $pagoId])->rowCount();
    if (!$n) throw new ErrorVenta('El pago ya no está pendiente.');
    bitacora("Pago #$pagoId recibido (ref. $referencia)", $uid);
}

/** Confirma el pago: descuenta stock, fija la tasa BCV y pasa la venta a «Pago verificado». */
function confirmar_pago(int $pagoId, int $uid): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pago = q('SELECT * FROM pagos WHERE id = ? FOR UPDATE', [$pagoId])->fetch();
        if (!$pago || $pago['estado'] !== 'Recibido') throw new ErrorVenta('Solo se confirma un pago marcado como recibido, con su referencia.');
        $v = q('SELECT * FROM ventas WHERE id = ? FOR UPDATE', [$pago['venta_id']])->fetch();
        if (!in_array($v['estado'], ['Nuevo', 'Esperando pago'], true)) throw new ErrorVenta("La venta está en estado «{$v['estado']}».");
        $tasa = tasa_vigente();
        if (!$tasa) throw new ErrorVenta('Registra la tasa BCV antes de confirmar pagos.');

        $items = q('SELECT i.producto_id, i.cantidad, p.nombre FROM venta_items i JOIN productos p ON p.id = i.producto_id WHERE i.venta_id = ? ORDER BY i.producto_id', [$v['id']])->fetchAll();
        foreach ($items as $it) {
            $stock = (int) q('SELECT stock FROM productos WHERE id = ? FOR UPDATE', [$it['producto_id']])->fetchColumn();
            if ($stock < (int) $it['cantidad']) throw new ErrorVenta("Stock insuficiente de {$it['nombre']} (hay $stock). Ajusta el pedido o repón stock.");
        }
        foreach ($items as $it) {
            q('UPDATE productos SET stock = stock - ? WHERE id = ?', [$it['cantidad'], $it['producto_id']]);
            q("INSERT INTO movimientos_stock (producto_id, cantidad, motivo, venta_id, usuario_id) VALUES (?, ?, 'venta', ?, ?)",
                [$it['producto_id'], -(int) $it['cantidad'], $v['id'], $uid]);
        }
        q("UPDATE pagos SET estado = 'Confirmado', confirmado_por = ?, confirmado_en = NOW() WHERE id = ?", [$uid, $pagoId]);
        q("UPDATE pagos SET estado = 'Rechazado' WHERE venta_id = ? AND id <> ? AND estado IN ('Pendiente','Recibido')", [$v['id'], $pagoId]);
        q("UPDATE ventas SET estado = 'Pago verificado', tasa_usd_bs = ?, total_bs = ? WHERE id = ?",
            [$tasa['usd_bs'], a_bs((float) $v['total_usd'], (float) $tasa['usd_bs']), $v['id']]);
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    bitacora("Confirmó pago #$pagoId de {$v['codigo']}", $uid);
}

function rechazar_pago(int $pagoId, int $uid): void
{
    $n = q("UPDATE pagos SET estado = 'Rechazado', confirmado_por = ?, confirmado_en = NOW() WHERE id = ? AND estado IN ('Pendiente','Recibido')", [$uid, $pagoId])->rowCount();
    if (!$n) throw new ErrorVenta('Ese pago ya fue procesado.');
    bitacora("Rechazó pago #$pagoId", $uid);
}

function nuevo_pago(int $ventaId, string $metodo, int $uid): void
{
    $v = q('SELECT estado FROM ventas WHERE id = ?', [$ventaId])->fetch();
    if (!$v || !in_array($v['estado'], ['Nuevo', 'Esperando pago'], true)) throw new ErrorVenta('La venta no admite nuevos pagos.');
    if (q("SELECT 1 FROM pagos WHERE venta_id = ? AND estado IN ('Pendiente','Recibido')", [$ventaId])->fetchColumn()) throw new ErrorVenta('Ya hay un pago abierto.');
    q('INSERT INTO pagos (venta_id, metodo, moneda) VALUES (?, ?, ?)', [$ventaId, $metodo, moneda_de($metodo)]);
    bitacora("Nuevo pago ($metodo) para venta #$ventaId", $uid);
}

function avanzar_estado(int $ventaId, int $uid): string
{
    $v = q('SELECT codigo, estado, envio_tipo FROM ventas WHERE id = ?', [$ventaId])->fetch();
    $sig = SIGUIENTE_ESTADO[$v['estado'] ?? ''] ?? null;
    if (!$sig) throw new ErrorVenta('Esta venta no puede avanzar de estado.');
    if ($sig === 'Despachado' && $v['envio_tipo'] === 'nacional' && !q('SELECT 1 FROM guias WHERE venta_id = ?', [$ventaId])->fetchColumn()) {
        throw new ErrorVenta('Genera la guía de envío antes de marcar como despachado.');
    }
    q('UPDATE ventas SET estado = ? WHERE id = ? AND estado = ?', [$sig, $ventaId, $v['estado']]);
    bitacora("{$v['codigo']}: {$v['estado']} → $sig", $uid);
    return $sig;
}

/** Cancela la venta; si ya se había descontado stock, lo devuelve. */
function cancelar_venta(int $ventaId, int $uid): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = q('SELECT * FROM ventas WHERE id = ? FOR UPDATE', [$ventaId])->fetch();
        if (!$v || in_array($v['estado'], ['Cancelado', 'Entregado'], true)) throw new ErrorVenta('Esta venta no se puede cancelar.');
        if (in_array($v['estado'], ESTADOS_CON_STOCK_DESCONTADO, true)) {
            foreach (q('SELECT producto_id, cantidad FROM venta_items WHERE venta_id = ?', [$ventaId])->fetchAll() as $it) {
                q('UPDATE productos SET stock = stock + ? WHERE id = ?', [$it['cantidad'], $it['producto_id']]);
                q("INSERT INTO movimientos_stock (producto_id, cantidad, motivo, venta_id, nota, usuario_id) VALUES (?, ?, 'devolucion', ?, 'Venta cancelada', ?)",
                    [$it['producto_id'], $it['cantidad'], $ventaId, $uid]);
            }
        }
        q("UPDATE pagos SET estado = 'Rechazado' WHERE venta_id = ? AND estado IN ('Pendiente','Recibido')", [$ventaId]);
        q("UPDATE ventas SET estado = 'Cancelado' WHERE id = ?", [$ventaId]);
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    bitacora("Canceló {$v['codigo']} (estaba en {$v['estado']})", $uid);
}

/** Crea la guía interna CP-G-#### de un envío nacional con pago confirmado. */
function crear_guia(int $ventaId, array $g, int $uid): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $v = q('SELECT * FROM ventas WHERE id = ? FOR UPDATE', [$ventaId])->fetch();
        if (!$v || $v['envio_tipo'] !== 'nacional') throw new ErrorVenta('La guía es solo para envíos nacionales.');
        if (!in_array($v['estado'], ['Pago verificado', 'Preparado'], true)) throw new ErrorVenta('Confirma el pago antes de generar la guía.');
        if (q('SELECT 1 FROM guias WHERE venta_id = ?', [$ventaId])->fetchColumn()) throw new ErrorVenta('Esta venta ya tiene guía.');
        q('INSERT INTO guias (venta_id, numero, empresa, dest_nombre, dest_cedula, dest_telefono, dest_estado, dest_ciudad, dest_direccion, modalidad_pago, bultos, peso_kg, creado_por)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$ventaId, 'T' . bin2hex(random_bytes(6)), $g['empresa'], $g['dest_nombre'], $g['dest_cedula'] ?: null, $g['dest_telefono'] ?: null,
             $g['dest_estado'], $g['dest_ciudad'], $g['dest_direccion'] ?: null, $g['modalidad_pago'], $g['bultos'], $g['peso_kg'], $uid]);
        $gid = (int) $pdo->lastInsertId();
        q('UPDATE guias SET numero = ? WHERE id = ?', [sprintf('CP-G-%04d', $gid), $gid]);
        q('UPDATE ventas SET envio_empresa = ? WHERE id = ?', [$g['empresa'], $ventaId]);
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
    bitacora(sprintf('Generó guía CP-G-%04d para %s (%s)', $gid, $v['codigo'], $g['empresa']), $uid);
    return $gid;
}
