<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (usuario()) ir('index.php');
verificar_post();

$error = '';
$correo = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $correo = mb_strtolower(txt('correo', 120));
    $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';
    $u = q('SELECT id, clave_hash, intentos, bloqueado_hasta FROM usuarios WHERE correo = ? AND activo = 1', [$correo])->fetch();

    if ($u && $u['bloqueado_hasta'] !== null && strtotime($u['bloqueado_hasta']) > time()) {
        $error = 'Demasiados intentos. Espera ' . MINUTOS_BLOQUEO . ' minutos e inténtalo de nuevo.';
    } else {
        // Se verifica siempre contra un hash para no revelar si el correo existe (mismo tiempo de respuesta)
        $hash = $u['clave_hash'] ?? '$2y$10$FEHi/qTjEawm7TfC/jFefu0IxTMXPCI0z9ssJmSD/MpLtnaP8fcMC';
        $valida = password_verify($clave, $hash) && $u;
        if ($valida) {
            if (password_needs_rehash($u['clave_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
            }
            q('UPDATE usuarios SET intentos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = ?', [$u['id']]);
            session_regenerate_id(true);
            $_SESSION = ['uid' => (int) $u['id'], 'visto' => time()];
            bitacora('Inició sesión', (int) $u['id']);
            ir('index.php');
        }
        if ($u) {
            $intentos = (int) $u['intentos'] + 1;
            if ($intentos >= MAX_INTENTOS) {
                q('UPDATE usuarios SET intentos = 0, bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?', [MINUTOS_BLOQUEO, $u['id']]);
                bitacora('Cuenta bloqueada por intentos fallidos', (int) $u['id']);
            } else {
                q('UPDATE usuarios SET intentos = ? WHERE id = ?', [$intentos, $u['id']]);
            }
        }
        usleep(random_int(200000, 400000));
        $error = 'Correo o contraseña incorrectos.';
    }
}

cabecera('Entrar');
?>
<form class="login" method="post" action="login.php" autocomplete="on">
  <img src="assets/logo.svg" alt="CTRL+" width="140" height="46">
  <h1>Panel</h1>
  <?php if ($error): ?><p class="flash bad" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?= csrf_field() ?>
  <label for="correo">Correo</label>
  <input id="correo" name="correo" type="email" required maxlength="120" autocomplete="username" value="<?= e($correo) ?>">
  <label for="clave">Contraseña</label>
  <input id="clave" name="clave" type="password" required maxlength="200" autocomplete="current-password">
  <button class="btn" type="submit">Entrar</button>
</form>
<?php pie();
