<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$u = requiere_sesion();
$esAdmin = $u['rol'] === 'admin';

$errores = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $accion = txt('accion', 20);
    if ($accion === 'clave') {
        $actual = is_string($_POST['actual'] ?? null) ? $_POST['actual'] : '';
        $nueva = is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '';
        $hash = q('SELECT clave_hash FROM usuarios WHERE id = ?', [$u['id']])->fetchColumn();
        if (!password_verify($actual, (string) $hash)) $errores[] = 'La contraseña actual no es correcta.';
        elseif (mb_strlen($nueva) < CLAVE_MINIMA) $errores[] = 'La nueva contraseña debe tener al menos ' . CLAVE_MINIMA . ' caracteres.';
        elseif ($nueva !== ($_POST['repite'] ?? null)) $errores[] = 'Las contraseñas nuevas no coinciden.';
        else {
            q('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            bitacora('Cambió su contraseña');
            flash('ok', 'Contraseña actualizada.');
            ir('usuarios.php');
        }
    } elseif ($accion === 'crear' && $esAdmin) {
        $nombre = txt('nombre', 60);
        $correo = mb_strtolower(txt('correo', 120));
        $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';
        $rol = uno_de(txt('rol', 10), ['admin', 'ventas', 'despacho'], 'ventas');
        if ($nombre === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = 'Nombre y correo válidos son obligatorios.';
        if (mb_strlen($clave) < CLAVE_MINIMA) $errores[] = 'La contraseña debe tener al menos ' . CLAVE_MINIMA . ' caracteres.';
        if (q('SELECT 1 FROM usuarios WHERE correo = ?', [$correo])->fetchColumn()) $errores[] = 'Ese correo ya tiene cuenta.';
        if (!$errores) {
            q('INSERT INTO usuarios (nombre, correo, clave_hash, rol) VALUES (?,?,?,?)', [$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), $rol]);
            bitacora("Creó usuario $correo ($rol)");
            flash('ok', 'Usuario creado. Comparte la contraseña en persona, no por chat.');
            ir('usuarios.php');
        }
    } elseif ($accion === 'activo' && $esAdmin) {
        $id = ent('id');
        if ($id === (int) $u['id']) $errores[] = 'No puedes desactivar tu propia cuenta.';
        else {
            q('UPDATE usuarios SET activo = 1 - activo, intentos = 0, bloqueado_hasta = NULL WHERE id = ?', [$id]);
            bitacora("Cambió acceso del usuario #$id");
            ir('usuarios.php');
        }
    }
}

$usuarios = q('SELECT id, nombre, correo, rol, activo, ultimo_acceso, bloqueado_hasta FROM usuarios ORDER BY nombre')->fetchAll();
$log = $esAdmin ? q('SELECT b.creado, b.accion, b.ip, u.nombre FROM bitacora b LEFT JOIN usuarios u ON u.id = b.usuario_id ORDER BY b.id DESC LIMIT 40')->fetchAll() : [];

cabecera('Usuarios', $u, 'usuarios.php');
?>
<h1>Usuarios</h1>
<?php foreach ($errores as $err): ?><p class="flash bad"><?= e($err) ?></p><?php endforeach; ?>
<div class="panel scroll">
  <table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Último acceso</th><th></th></tr></thead><tbody>
  <?php foreach ($usuarios as $x): ?>
    <tr<?= $x['activo'] ? '' : ' class="apagado"' ?>><td><?= e($x['nombre']) ?></td><td><?= e($x['correo']) ?></td><td><?= e($x['rol']) ?></td>
      <td><?= $x['ultimo_acceso'] ? e(date('d/m/y H:i', strtotime($x['ultimo_acceso']))) : '—' ?><?= $x['bloqueado_hasta'] && strtotime($x['bloqueado_hasta']) > time() ? ' <span class="badge bad">bloqueado</span>' : '' ?></td>
      <td><?php if ($esAdmin && (int) $x['id'] !== (int) $u['id']): ?><form method="post" action="usuarios.php"><?= csrf_field() ?><input type="hidden" name="accion" value="activo"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="btn sec chico" type="submit"><?= $x['activo'] ? 'Desactivar' : 'Activar' ?></button></form><?php endif; ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<div class="grid2">
  <form class="panel" method="post" action="usuarios.php">
    <h2>Cambiar mi contraseña</h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="clave">
    <label for="actual">Actual</label><input id="actual" name="actual" type="password" required autocomplete="current-password">
    <label for="nueva">Nueva (mín. <?= CLAVE_MINIMA ?>)</label><input id="nueva" name="nueva" type="password" required minlength="<?= CLAVE_MINIMA ?>" autocomplete="new-password">
    <label for="repite">Repite la nueva</label><input id="repite" name="repite" type="password" required minlength="<?= CLAVE_MINIMA ?>" autocomplete="new-password">
    <p><button class="btn" type="submit">Cambiar</button></p>
  </form>
  <?php if ($esAdmin): ?>
  <form class="panel" method="post" action="usuarios.php">
    <h2>Nuevo usuario</h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="crear">
    <label for="nombre">Nombre</label><input id="nombre" name="nombre" required maxlength="60">
    <label for="correo">Correo</label><input id="correo" name="correo" type="email" required maxlength="120">
    <label for="rol">Rol</label><select id="rol" name="rol"><?= opciones(['admin' => 'Admin (todo)', 'ventas' => 'Ventas', 'despacho' => 'Despacho'], 'ventas') ?></select>
    <label for="clave">Contraseña inicial</label><input id="clave" name="clave" type="password" required minlength="<?= CLAVE_MINIMA ?>" autocomplete="new-password">
    <p><button class="btn" type="submit">Crear</button></p>
  </form>
  <?php endif; ?>
</div>
<?php if ($log): ?>
<div class="panel scroll">
  <h2>Bitácora reciente</h2>
  <table class="mini"><tbody><?php foreach ($log as $l): ?><tr><td><?= e(date('d/m/y H:i', strtotime($l['creado']))) ?></td><td><?= e($l['nombre'] ?? '—') ?></td><td><?= e($l['accion']) ?></td><td class="mono hint"><?= e($l['ip']) ?></td></tr><?php endforeach; ?></tbody></table>
</div>
<?php endif; ?>
<?php pie($u);
