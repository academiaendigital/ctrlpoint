<?php
/**
 * Instalador de un solo uso: crea las cuentas admin (Jenny y Rubén).
 * Solo funciona si no existe ninguna usuaria y con la clave_instalacion de config.php.
 * Después de usarlo, borra este archivo del servidor y vacía clave_instalacion.
 */
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
verificar_post();

$hay = (int) q('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$claveInst = (string) (CFG['clave_instalacion'] ?? '');
if ($hay > 0 || strlen($claveInst) < 16) {
    http_response_code(404);
    exit('No disponible.');
}

$errores = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($claveInst, is_string($_POST['instalacion'] ?? null) ? $_POST['instalacion'] : '')) {
        $errores[] = 'Clave de instalación incorrecta.';
    }
    $cuentas = [];
    foreach ([1, 2] as $i) {
        $nombre = txt("nombre$i", 60);
        $correo = mb_strtolower(txt("correo$i", 120));
        $clave = is_string($_POST["clave$i"] ?? null) ? $_POST["clave$i"] : '';
        if ($i === 2 && $nombre === '' && $correo === '') continue;
        if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "Cuenta $i: nombre y correo válidos son obligatorios.";
        if (mb_strlen($clave) < CLAVE_MINIMA) $errores[] = "Cuenta $i: la contraseña debe tener al menos " . CLAVE_MINIMA . ' caracteres.';
        $cuentas[] = [$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT)];
    }
    if (count($cuentas) === 2 && $cuentas[0][1] === $cuentas[1][1]) $errores[] = 'Los correos deben ser distintos.';
    if (!$errores) {
        foreach ($cuentas as [$n, $c, $h]) {
            q("INSERT INTO usuarios (nombre, correo, clave_hash, rol) VALUES (?, ?, ?, 'admin')", [$n, $c, $h]);
            bitacora("Instalación: creada cuenta admin $c", (int) db()->lastInsertId());
        }
        flash('ok', 'Cuentas creadas. Borra instalar.php del servidor y entra con tu correo.');
        ir('login.php');
    }
}

cabecera('Instalar');
?>
<form class="login ancho" method="post" action="instalar.php" autocomplete="off">
  <img src="assets/logo.svg" alt="CTRL+" width="140" height="46">
  <h1>Instalación del panel</h1>
  <?php foreach ($errores as $err): ?><p class="flash bad"><?= e($err) ?></p><?php endforeach; ?>
  <?= csrf_field() ?>
  <label for="instalacion">Clave de instalación (de config.php)</label>
  <input id="instalacion" name="instalacion" type="password" required>
  <?php foreach ([1 => 'Admin 1 (Jenny)', 2 => 'Admin 2 (Rubén, opcional)'] as $i => $t): ?>
    <fieldset>
      <legend><?= e($t) ?></legend>
      <label for="nombre<?= $i ?>">Nombre</label>
      <input id="nombre<?= $i ?>" name="nombre<?= $i ?>" maxlength="60" <?= $i === 1 ? 'required' : '' ?>>
      <label for="correo<?= $i ?>">Correo</label>
      <input id="correo<?= $i ?>" name="correo<?= $i ?>" type="email" maxlength="120" <?= $i === 1 ? 'required' : '' ?>>
      <label for="clave<?= $i ?>">Contraseña (mín. <?= CLAVE_MINIMA ?>)</label>
      <input id="clave<?= $i ?>" name="clave<?= $i ?>" type="password" minlength="<?= CLAVE_MINIMA ?>" autocomplete="new-password" <?= $i === 1 ? 'required' : '' ?>>
    </fieldset>
  <?php endforeach; ?>
  <button class="btn" type="submit">Crear cuentas</button>
</form>
<?php pie();
