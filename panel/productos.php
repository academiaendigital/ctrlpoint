<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion();

$busca = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';
$cat = ent('cat', $_GET);
$verOcultos = ($_GET['ocultos'] ?? '') === '1';

$sql = 'SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE 1=1';
$par = [];
if (!$verOcultos) $sql .= ' AND p.activo = 1';
if ($cat > 0) { $sql .= ' AND p.categoria_id = ?'; $par[] = $cat; }
if ($busca !== '') { $sql .= ' AND (p.nombre LIKE ? OR p.sku LIKE ?)'; $like = '%' . addcslashes($busca, '%_\\') . '%'; $par[] = $like; $par[] = $like; }
$sql .= ' ORDER BY c.nombre, p.nombre';
$productos = q($sql, $par)->fetchAll();
$categorias = q('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$tasa = tasa_vigente();

// Catálogo público para la tienda: SIN costos ni márgenes
if (($_GET['exportar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="catalogo-ctrlplus-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['sku', 'slug', 'nombre', 'categoria', 'precio_usd', 'precio_bs', 'stock'], ';');
    foreach ($productos as $p) {
        if (!$p['activo']) continue;
        $pv = precio_venta($p['costo_usd'], $p['margen']);
        fputcsv($out, [$p['sku'], $p['slug'], $p['nombre'], $p['categoria'], number_format($pv, 2, '.', ''), $tasa ? number_format(a_bs($pv, (float) $tasa['usd_bs']), 2, '.', '') : '', $p['stock']], ';');
    }
    exit;
}

cabecera('Inventario', $u, 'productos.php');
?>
<div class="titulo">
  <h1>Inventario</h1>
  <div class="acciones">
    <a class="btn sec" href="productos.php?exportar=csv">Exportar catálogo (sin costos)</a>
    <a class="btn" href="producto.php">+ Nuevo producto</a>
  </div>
</div>
<form class="panel row" method="get" action="productos.php">
  <div><label for="q">Buscar</label><input id="q" name="q" value="<?= e($busca) ?>" placeholder="Nombre o SKU"></div>
  <div><label for="cat">Categoría</label><select id="cat" name="cat"><option value="0">Todas</option>
    <?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id'] ?>"<?= $cat === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select></div>
  <div class="check"><label><input type="checkbox" name="ocultos" value="1"<?= $verOcultos ? ' checked' : '' ?>> Ver ocultos</label></div>
  <div><button class="btn sec" type="submit">Filtrar</button></div>
</form>
<div class="panel scroll">
  <table>
    <thead><tr><th>SKU</th><th>Producto</th><th>Categoría</th><th class="num">Costo</th><th class="num">Margen</th><th class="num">Venta USD</th><th class="num">Venta Bs</th><th class="num">Stock</th></tr></thead>
    <tbody>
    <?php foreach ($productos as $p):
        $pv = precio_venta($p['costo_usd'], $p['margen']); ?>
      <tr<?= $p['activo'] ? '' : ' class="apagado"' ?>>
        <td class="mono"><?= e($p['sku']) ?></td>
        <td><a href="producto.php?id=<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?></a><?= $p['activo'] ? '' : ' <span class="badge">oculto</span>' ?></td>
        <td><?= e($p['categoria']) ?></td>
        <td class="num"><?= usd($p['costo_usd']) ?></td>
        <td class="num"><?= e(rtrim(rtrim(number_format(margen_de($p['margen']), 2, ',', ''), '0'), ',')) ?> %<?= $p['margen'] === null ? '' : ' <span class="badge info" title="Margen propio">propio</span>' ?></td>
        <td class="num"><strong><?= usd($pv) ?></strong></td>
        <td class="num"><?= $tasa ? bs(a_bs($pv, (float) $tasa['usd_bs'])) : '—' ?></td>
        <td class="num"><?= $p['stock'] <= $p['stock_minimo'] ? '<span class="badge ' . ($p['stock'] == 0 ? 'bad' : 'warn') . '">' . (int) $p['stock'] . '</span>' : (int) $p['stock'] ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$productos): ?><tr><td colspan="8" class="hint">No hay productos con ese filtro.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php pie($u);
