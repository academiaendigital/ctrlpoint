<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion('ventas');

const FILAS = 8;
$errores = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $d = [
        'cliente' => txt('cliente', 80),
        'telefono' => preg_replace('/[^0-9+ -]/', '', txt('telefono', 20)),
        'envio_tipo' => uno_de(txt('envio_tipo', 10), ['retiro', 'delivery', 'nacional'], 'retiro'),
        'envio_ciudad' => txt('envio_ciudad', 60),
        'envio_direccion' => txt('envio_direccion', 100),
        'envio_empresa' => uno_de(txt('envio_empresa', 10), EMPRESAS_ENVIO, 'Zoom'),
        'nota' => txt('nota', 255),
    ];
    $metodo = uno_de(txt('metodo', 20), METODOS_PAGO, 'Pago Móvil');
    if ($d['cliente'] === '') $errores[] = 'El nombre del cliente es obligatorio.';
    if ($d['envio_tipo'] === 'nacional' && $d['envio_ciudad'] === '') $errores[] = 'Para envío nacional indica la ciudad.';
    if ($d['envio_tipo'] === 'delivery' && $d['envio_direccion'] === '') $errores[] = 'Para delivery indica el sector o dirección.';

    $items = [];
    $prod = is_array($_POST['producto'] ?? null) ? $_POST['producto'] : [];
    $cant = is_array($_POST['cantidad'] ?? null) ? $_POST['cantidad'] : [];
    for ($i = 0; $i < FILAS; $i++) {
        $pid = (int) (is_string($prod[$i] ?? null) ? $prod[$i] : 0);
        $c = (int) (is_string($cant[$i] ?? null) ? $cant[$i] : 0);
        if ($pid > 0 && $c > 0 && $c <= 999) $items[$pid] = ($items[$pid] ?? 0) + $c;
    }
    $lista = [];
    foreach ($items as $pid => $c) $lista[] = [$pid, $c];

    if (!$errores) {
        try {
            $vid = crear_venta($d, $lista, $metodo, (int) $u['id']);
            flash('ok', 'Venta creada. Cuando el cliente pague, registra la referencia.');
            ir('venta.php?id=' . $vid);
        } catch (ErrorVenta $ex) {
            $errores[] = $ex->getMessage();
        }
    }
}

$productos = q('SELECT id, sku, nombre, costo_usd, margen, stock FROM productos WHERE activo = 1 AND stock > 0 ORDER BY nombre')->fetchAll();
$val = fn(string $k, string $def = '') => e(is_string($_POST[$k] ?? null) ? $_POST[$k] : $def);

cabecera('Nueva venta', $u, 'ventas.php');
?>
<p><a href="ventas.php">← Ventas</a></p>
<h1>Nueva venta</h1>
<?php foreach ($errores as $err): ?><p class="flash bad"><?= e($err) ?></p><?php endforeach; ?>
<form method="post" action="venta_nueva.php" class="pila">
  <?= csrf_field() ?>
  <div class="panel">
    <h2>Cliente</h2>
    <div class="row">
      <div><label for="cliente">Nombre</label><input id="cliente" name="cliente" required maxlength="80" value="<?= $val('cliente') ?>"></div>
      <div><label for="telefono">Teléfono / WhatsApp</label><input id="telefono" name="telefono" maxlength="20" inputmode="tel" value="<?= $val('telefono') ?>"></div>
      <div><label for="metodo">Método de pago</label><select id="metodo" name="metodo"><?= opciones(METODOS_PAGO, $_POST['metodo'] ?? 'Pago Móvil') ?></select></div>
    </div>
  </div>
  <div class="panel">
    <h2>Productos</h2>
    <p class="hint">Precio calculado al momento (costo + margen, redondeado). Solo aparecen productos con stock.</p>
    <?php for ($i = 0; $i < FILAS; $i++): ?>
      <div class="row item">
        <div class="ancho2"><select name="producto[]" aria-label="Producto <?= $i + 1 ?>"><option value="0">—</option>
          <?php foreach ($productos as $p): ?><option value="<?= (int) $p['id'] ?>"<?= (string) ($_POST['producto'][$i] ?? '') === (string) $p['id'] ? ' selected' : '' ?>><?= e($p['nombre']) ?> · <?= usd(precio_venta($p['costo_usd'], $p['margen'])) ?> · stock <?= (int) $p['stock'] ?></option><?php endforeach; ?>
        </select></div>
        <div class="chico"><input name="cantidad[]" type="number" min="1" max="999" value="<?= e(is_string($_POST['cantidad'][$i] ?? null) ? $_POST['cantidad'][$i] : '1') ?>" aria-label="Cantidad <?= $i + 1 ?>"></div>
      </div>
    <?php endfor; ?>
  </div>
  <div class="panel">
    <h2>Entrega</h2>
    <div class="row">
      <div><label for="envio_tipo">Tipo</label><select id="envio_tipo" name="envio_tipo"><?= opciones(['retiro' => 'Retiro en tienda', 'delivery' => 'Delivery en San Juan de los Morros', 'nacional' => 'Envío nacional'], $_POST['envio_tipo'] ?? 'retiro') ?></select></div>
      <div><label for="envio_empresa">Empresa (nacional)</label><select id="envio_empresa" name="envio_empresa"><?= opciones(EMPRESAS_ENVIO, $_POST['envio_empresa'] ?? 'Zoom') ?></select></div>
      <div><label for="envio_ciudad">Ciudad (nacional)</label><input id="envio_ciudad" name="envio_ciudad" maxlength="60" value="<?= $val('envio_ciudad') ?>"></div>
      <div><label for="envio_direccion">Sector / dirección (delivery)</label><input id="envio_direccion" name="envio_direccion" maxlength="100" value="<?= $val('envio_direccion') ?>"></div>
    </div>
    <label for="nota">Nota interna</label><input id="nota" name="nota" maxlength="255" value="<?= $val('nota') ?>">
  </div>
  <p><button class="btn" type="submit">Crear venta</button></p>
</form>
<?php pie($u);
