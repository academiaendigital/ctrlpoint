<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion();

$id = ent('id', $_GET);
$p = $id ? q('SELECT * FROM productos WHERE id = ?', [$id])->fetch() : null;
if ($id && !$p) { http_response_code(404); exit('Producto no encontrado.'); }

function slug(string $s): string
{
    $s = mb_strtolower($s);
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', '&' => 'y']);
    return trim(substr(preg_replace('/[^a-z0-9]+/', '-', $s), 0, 80), '-');
}

$errores = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $accion = txt('accion', 20);

    if ($accion === 'guardar') {
        $datos = [
            'sku' => strtoupper(txt('sku', 20)),
            'nombre' => txt('nombre', 160),
            'slug' => slug(txt('slug', 80) ?: txt('nombre', 160)),
            'costo_usd' => dec('costo_usd'),
            'margen' => txt('margen') === '' ? null : dec('margen'),
            'stock_minimo' => max(0, ent('stock_minimo')),
            'activo' => isset($_POST['activo']) ? 1 : 0,
        ];
        $catId = ent('categoria_id');
        $catNueva = txt('categoria_nueva', 60);
        if (!preg_match('/^[A-Z0-9-]{2,20}$/', $datos['sku'])) $errores[] = 'SKU: solo letras, números y guiones (ej. CP-015).';
        if ($datos['nombre'] === '') $errores[] = 'El nombre es obligatorio.';
        if ($datos['costo_usd'] === null || $datos['costo_usd'] < 0) $errores[] = 'Costo en USD no válido.';
        if (txt('margen') !== '' && ($datos['margen'] === null || $datos['margen'] < 0 || $datos['margen'] > 500)) $errores[] = 'Margen entre 0 y 500 %, o vacío para usar el global.';
        if ($catNueva === '' && !q('SELECT 1 FROM categorias WHERE id = ?', [$catId])->fetchColumn()) $errores[] = 'Elige o escribe una categoría.';
        $dup = q('SELECT id FROM productos WHERE (sku = ? OR slug = ?) AND id <> ?', [$datos['sku'], $datos['slug'], $id])->fetchColumn();
        if ($dup) $errores[] = 'Ya existe otro producto con ese SKU o slug.';

        if (!$errores) {
            if ($catNueva !== '') {
                q('INSERT INTO categorias (nombre) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)', [$catNueva]);
                $catId = (int) db()->lastInsertId();
            }
            $datos['categoria_id'] = $catId;
            if ($p) {
                q('UPDATE productos SET sku=?, nombre=?, slug=?, costo_usd=?, margen=?, stock_minimo=?, activo=?, categoria_id=? WHERE id=?',
                    [...array_values($datos), $id]);
                $cambios = [];
                if ((float) $p['costo_usd'] !== (float) $datos['costo_usd']) $cambios[] = "costo {$p['costo_usd']}→{$datos['costo_usd']}";
                if ($p['margen'] !== ($datos['margen'] === null ? null : number_format($datos['margen'], 2, '.', ''))) $cambios[] = 'margen ' . ($p['margen'] ?? 'global') . '→' . ($datos['margen'] ?? 'global');
                bitacora("Editó producto {$datos['sku']}" . ($cambios ? ' (' . implode(', ', $cambios) . ')' : ''));
            } else {
                $stockInicial = max(0, ent('stock_inicial'));
                db()->beginTransaction();
                q('INSERT INTO productos (sku, nombre, slug, costo_usd, margen, stock_minimo, activo, categoria_id, stock) VALUES (?,?,?,?,?,?,?,?,?)',
                    [...array_values($datos), $stockInicial]);
                $id = (int) db()->lastInsertId();
                if ($stockInicial > 0) q("INSERT INTO movimientos_stock (producto_id, cantidad, motivo, nota, usuario_id) VALUES (?, ?, 'compra', 'Stock inicial', ?)", [$id, $stockInicial, $u['id']]);
                db()->commit();
                bitacora("Creó producto {$datos['sku']} (costo {$datos['costo_usd']})");
            }
            flash('ok', 'Producto guardado. Precio de venta: ' . usd(precio_venta($datos['costo_usd'], $datos['margen'] === null ? null : (string) $datos['margen'])));
            ir('producto.php?id=' . $id);
        }
    } elseif ($accion === 'stock' && $p) {
        $cant = ent('cantidad');
        $motivo = uno_de(txt('motivo', 20), ['compra', 'ajuste', 'devolucion'], 'ajuste');
        $nota = txt('nota', 160);
        if ($cant === 0) {
            $errores[] = 'Indica una cantidad distinta de cero (negativa para restar).';
        } else {
            db()->beginTransaction();
            $ok = $cant > 0
                ? q('UPDATE productos SET stock = stock + ? WHERE id = ?', [$cant, $id])->rowCount()
                : q('UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?', [-$cant, $id, -$cant])->rowCount();
            if ($ok) {
                q('INSERT INTO movimientos_stock (producto_id, cantidad, motivo, nota, usuario_id) VALUES (?,?,?,?,?)', [$id, $cant, $motivo, $nota ?: null, $u['id']]);
                db()->commit();
                bitacora("Stock {$p['sku']}: " . ($cant > 0 ? '+' : '') . "$cant ($motivo)");
                flash('ok', 'Movimiento registrado.');
                ir('producto.php?id=' . $id);
            }
            db()->rollBack();
            $errores[] = 'No hay suficiente stock para restar esa cantidad.';
        }
    }
}

$categorias = q('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$movs = $p ? q('SELECT m.*, u.nombre AS usuario, v.codigo FROM movimientos_stock m LEFT JOIN usuarios u ON u.id = m.usuario_id LEFT JOIN ventas v ON v.id = m.venta_id WHERE m.producto_id = ? ORDER BY m.id DESC LIMIT 30', [$id])->fetchAll() : [];
$v = fn(string $k, $d = '') => e($_POST[$k] ?? $p[$k] ?? $d);
$tasa = tasa_vigente();

cabecera($p ? $p['nombre'] : 'Nuevo producto', $u, 'productos.php');
?>
<p><a href="productos.php">← Inventario</a></p>
<h1><?= $p ? e($p['nombre']) : 'Nuevo producto' ?></h1>
<?php foreach ($errores as $err): ?><p class="flash bad"><?= e($err) ?></p><?php endforeach; ?>

<form class="panel" method="post" action="producto.php<?= $p ? '?id=' . $id : '' ?>">
  <?= csrf_field() ?><input type="hidden" name="accion" value="guardar">
  <div class="row">
    <div><label for="sku">SKU</label><input id="sku" name="sku" required maxlength="20" value="<?= $v('sku') ?>" placeholder="CP-015"></div>
    <div class="ancho2"><label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="160" value="<?= $v('nombre') ?>"></div>
  </div>
  <div class="row">
    <div><label for="categoria_id">Categoría</label><select id="categoria_id" name="categoria_id">
      <?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (string) ($_POST['categoria_id'] ?? $p['categoria_id'] ?? '') === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
    </select></div>
    <div><label for="categoria_nueva">…o nueva categoría</label><input id="categoria_nueva" name="categoria_nueva" maxlength="60" value="<?= e($_POST['categoria_nueva'] ?? '') ?>"></div>
    <div><label for="slug">Slug (URL)</label><input id="slug" name="slug" maxlength="80" value="<?= $v('slug') ?>" placeholder="se genera del nombre"></div>
  </div>
  <div class="row" id="calc" data-margen="<?= e(ajuste('margen_ganancia', '30')) ?>" data-redondeo="<?= e(ajuste('redondeo_usd', '1')) ?>" data-tasa="<?= e($tasa['usd_bs'] ?? '') ?>" data-dif="<?= e(ajuste('diferencial_bs')) ?>">
    <div><label for="costo_usd">Costo USD</label><input id="costo_usd" name="costo_usd" required inputmode="decimal" value="<?= $v('costo_usd') ?>"></div>
    <div><label for="margen">Margen % (vacío = global <?= e(ajuste('margen_ganancia', '30')) ?> %)</label><input id="margen" name="margen" inputmode="decimal" value="<?= $v('margen') ?>"></div>
    <div><label for="stock_minimo">Stock mínimo</label><input id="stock_minimo" name="stock_minimo" type="number" min="0" value="<?= $v('stock_minimo', '1') ?>"></div>
    <?php if (!$p): ?><div><label for="stock_inicial">Stock inicial</label><input id="stock_inicial" name="stock_inicial" type="number" min="0" value="<?= e($_POST['stock_inicial'] ?? '0') ?>"></div><?php endif; ?>
  </div>
  <p class="precio">Precio de venta: <strong id="pv"><?= $p ? usd(precio_venta($p['costo_usd'], $p['margen'])) : '—' ?></strong> <span id="pvbs" class="hint"><?= $p && $tasa ? bs(a_bs(precio_venta($p['costo_usd'], $p['margen']), (float) $tasa['usd_bs'])) : '' ?></span></p>
  <div class="row">
    <div class="check"><label><input type="checkbox" name="activo" value="1"<?= ($p['activo'] ?? 1) ? ' checked' : '' ?>> Visible en la tienda</label></div>
    <div><button class="btn" type="submit">Guardar</button></div>
  </div>
</form>

<?php if ($p): ?>
<div class="grid2">
  <form class="panel" method="post" action="producto.php?id=<?= $id ?>">
    <h2>Movimiento de stock · actual: <?= (int) $p['stock'] ?></h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="stock">
    <div class="row">
      <div><label for="cantidad">Cantidad (+ entra, − sale)</label><input id="cantidad" name="cantidad" type="number" required></div>
      <div><label for="motivo">Motivo</label><select id="motivo" name="motivo"><?= opciones(['compra' => 'Compra / reposición', 'ajuste' => 'Ajuste (conteo, daño)', 'devolucion' => 'Devolución'], 'compra') ?></select></div>
    </div>
    <label for="nota">Nota</label><input id="nota" name="nota" maxlength="160">
    <p><button class="btn" type="submit">Registrar</button></p>
    <p class="hint">Las ventas descuentan stock solas al confirmar el pago.</p>
  </form>
  <div class="panel scroll">
    <h2>Historial</h2>
    <table><thead><tr><th>Fecha</th><th class="num">Cant.</th><th>Motivo</th><th>Detalle</th></tr></thead><tbody>
    <?php foreach ($movs as $m): ?>
      <tr><td><?= e(date('d/m/y H:i', strtotime($m['creado']))) ?></td><td class="num"><?= $m['cantidad'] > 0 ? '+' : '' ?><?= (int) $m['cantidad'] ?></td><td><?= e($m['motivo']) ?></td>
      <td><?= $m['codigo'] ? '<a href="venta.php?id=' . (int) $m['venta_id'] . '">' . e($m['codigo']) . '</a> ' : '' ?><?= e($m['nota']) ?> <span class="hint"><?= e($m['usuario']) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
</div>
<?php endif; ?>
<?php pie($u);
