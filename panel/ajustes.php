<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion('admin');

$errores = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $accion = txt('accion', 20);
    if ($accion === 'precios') {
        $nuevos = [
            'margen_ganancia' => [dec('margen_ganancia'), 0, 500],
            'redondeo_usd' => [dec('redondeo_usd'), 0.01, 100],
            'diferencial_bs' => [dec('diferencial_bs'), -50, 100],
            'diferencial_eur' => [dec('diferencial_eur'), -50, 100],
        ];
        foreach ($nuevos as $k => [$val, $min, $max]) {
            if ($val === null || $val < $min || $val > $max) $errores[] = "Valor no válido para $k (entre $min y $max).";
        }
        if (!$errores) {
            foreach ($nuevos as $k => [$val]) {
                $antes = ajuste($k);
                $txt = rtrim(rtrim(number_format($val, 4, '.', ''), '0'), '.');
                q('UPDATE ajustes SET valor = ? WHERE clave = ?', [$txt, $k]);
                if ($antes !== $txt) bitacora("Ajuste $k: $antes → $txt");
            }
            flash('ok', 'Ajustes de precio guardados. Todos los precios se recalcularon.');
            ir('ajustes.php');
        }
    } elseif ($accion === 'tasa') {
        $fecha = txt('fecha', 10);
        $usdBs = dec('usd_bs');
        $eurBs = dec('eur_bs');
        $f = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$f || $f->format('Y-m-d') !== $fecha || $f > new DateTime('tomorrow')) $errores[] = 'Fecha no válida.';
        if ($usdBs === null || $usdBs <= 0) $errores[] = 'Tasa USD no válida.';
        if ($eurBs === null || $eurBs <= 0) $errores[] = 'Tasa EUR no válida.';
        if (!$errores) {
            q('INSERT INTO tasas (fecha, usd_bs, eur_bs) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE usd_bs = VALUES(usd_bs), eur_bs = VALUES(eur_bs)', [$fecha, $usdBs, $eurBs]);
            bitacora("Tasa BCV $fecha: USD $usdBs, EUR $eurBs");
            flash('ok', 'Tasa BCV registrada.');
            ir('ajustes.php');
        }
    }
}

$tasas = q('SELECT * FROM tasas ORDER BY fecha DESC LIMIT 10')->fetchAll();
$a = ajustes();
$ej = calcular_precio(70, (float) $a['margen_ganancia'], (float) $a['redondeo_usd']);

cabecera('Precios y tasa', $u, 'ajustes.php');
?>
<h1>Precios y tasa</h1>
<?php foreach ($errores as $err): ?><p class="flash bad"><?= e($err) ?></p><?php endforeach; ?>
<div class="grid2">
  <form class="panel" method="post" action="ajustes.php">
    <h2>Precio de venta</h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="precios">
    <div class="row">
      <div><label for="margen_ganancia">Margen global %</label><input id="margen_ganancia" name="margen_ganancia" inputmode="decimal" required value="<?= e($a['margen_ganancia']) ?>"></div>
      <div><label for="redondeo_usd">Redondear hacia arriba a</label><select id="redondeo_usd" name="redondeo_usd"><?= opciones(['0.01' => 'Sin redondeo (centavos)', '1' => '$1 (dólar entero)', '5' => 'Múltiplo de $5', '10' => 'Múltiplo de $10'], rtrim(rtrim($a['redondeo_usd'], '0'), '.') ?: '1') ?></select></div>
    </div>
    <div class="row">
      <div><label for="diferencial_bs">Diferencial Bs %</label><input id="diferencial_bs" name="diferencial_bs" inputmode="decimal" required value="<?= e($a['diferencial_bs']) ?>"></div>
      <div><label for="diferencial_eur">Diferencial EUR %</label><input id="diferencial_eur" name="diferencial_eur" inputmode="decimal" required value="<?= e($a['diferencial_eur']) ?>"></div>
    </div>
    <p class="hint">Ejemplo: costo $70 → venta <?= usd($ej) ?>. Cada producto puede tener su propio margen.</p>
    <button class="btn" type="submit">Guardar</button>
  </form>

  <form class="panel" method="post" action="ajustes.php">
    <h2>Tasa BCV del día</h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="tasa">
    <div class="row">
      <div><label for="fecha">Fecha</label><input id="fecha" name="fecha" type="date" required value="<?= e(date('Y-m-d')) ?>"></div>
      <div><label for="usd_bs">Bs por 1 USD</label><input id="usd_bs" name="usd_bs" inputmode="decimal" required></div>
      <div><label for="eur_bs">Bs por 1 EUR</label><input id="eur_bs" name="eur_bs" inputmode="decimal" required></div>
    </div>
    <p class="hint">Copia la tasa oficial publicada en bcv.org.ve. Cada venta guarda la tasa del día en que se confirma el pago.</p>
    <button class="btn" type="submit">Registrar tasa</button>
    <table class="mini"><thead><tr><th>Fecha</th><th class="num">USD</th><th class="num">EUR</th></tr></thead><tbody>
      <?php foreach ($tasas as $t): ?><tr><td><?= e(date('d/m/Y', strtotime($t['fecha']))) ?></td><td class="num"><?= e(number_format((float) $t['usd_bs'], 4, ',', '.')) ?></td><td class="num"><?= e(number_format((float) $t['eur_bs'], 4, ',', '.')) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </form>
</div>
<?php pie($u);
