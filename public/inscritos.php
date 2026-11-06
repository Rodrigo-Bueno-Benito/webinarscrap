<?php
/**
 * Panel privado de inscripciones: entra con ADMIN_PASSWORD (.env), muestra los inscritos
 * de cada sesión y permite descargarlos en CSV para Excel.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/inscripciones.php';

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('webinars_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $https,
    'samesite' => 'Strict',
]);
session_start();

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');

$password = config('ADMIN_PASSWORD');
$error = '';

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function etiqueta(string $sesion): string
{
    return explode(' — ', SESIONES[$sesion] ?? $sesion)[0];
}

function fecha_legible(string $iso): string
{
    $t = strtotime($iso);
    return $t ? date('d/m/Y H:i', $t) : $iso;
}

/** Evita que Excel interprete como fórmula un valor que empiece por = + - @ */
function celda(string $valor): string
{
    return preg_match('/^[=+\-@\t\r]/', $valor) ? "'" . $valor : $valor;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'entrar' && $password !== '') {
        if (hash_equals($password, (string) ($_POST['clave'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            header('Location: inscritos.php', true, 303);
            exit;
        }
        sleep(1);   // frena los intentos a ciegas
        $error = 'Contraseña incorrecta.';
    } elseif ($accion === 'salir') {
        $_SESSION = [];
        session_destroy();
        header('Location: inscritos.php', true, 303);
        exit;
    }
}

$dentro = $password !== '' && !empty($_SESSION['admin']);

// Inscripciones agrupadas por sesión, en orden de llegada.
$porSesion = array_fill_keys(array_keys(SESIONES), []);
if ($dentro) {
    foreach (leer_inscripciones() as $fila) {
        if (isset($porSesion[$fila['sesion']])) {
            $porSesion[$fila['sesion']][] = $fila;
        }
    }
}

if ($dentro && ($_GET['descargar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inscripciones-webinars-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM: Excel abre bien las tildes
    fputcsv($out, ['Sesión', 'Nº', 'Nombre y apellidos', 'Empresa', 'Cargo', 'Correo', 'Fecha de inscripción', 'Política de Privacidad informada'], ';', '"', '');
    foreach ($porSesion as $sesion => $filas) {
        foreach ($filas as $i => $f) {
            fputcsv($out, array_map('celda', [
                etiqueta($sesion), (string) ($i + 1), $f['nombre'], $f['empresa'], $f['cargo'], $f['correo'], fecha_legible($f['fecha']), $f['politica'] ?? '',
            ]), ';', '"', '');
        }
    }
    fclose($out);
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Inscripciones · Webinars SCRAPenvases.com</title>
  <link rel="icon" href="favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..800&family=IBM+Plex+Mono:wght@500&display=swap">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header class="nav">
    <div class="wrap nav__in">
      <a class="nav__logo" href="./" aria-label="Volver a los webinars">
        <img src="img/logo-scrapenvases.png" alt="SCRAPenvases.com" width="760" height="59">
      </a>
      <span class="nav__title">Inscripciones</span>
      <?php if ($dentro): ?>
        <form method="post" class="nav__out">
          <input type="hidden" name="accion" value="salir">
          <button type="submit" class="btn btn--ghost btn--sm">Salir</button>
        </form>
      <?php endif; ?>
    </div>
  </header>

  <main class="wrap admin">
  <?php if ($password === ''): ?>

    <section class="card admin__login">
      <h1>Panel <span class="hl">desactivado</span></h1>
      <p class="admin__text">Para ver las inscripciones, define <code>ADMIN_PASSWORD</code> en el archivo <code>.env</code> del servidor.</p>
    </section>

  <?php elseif (!$dentro): ?>

    <form class="card admin__login" method="post">
      <h1>Inscripciones <span class="hl">a los webinars</span></h1>
      <input type="hidden" name="accion" value="entrar">
      <div class="field">
        <label for="clave">Contraseña</label>
        <input id="clave" name="clave" type="password" autocomplete="current-password" required autofocus>
      </div>
      <?php if ($error !== ''): ?>
        <p class="form-error" role="alert"><?= e($error) ?></p>
      <?php endif; ?>
      <div class="actions">
        <button type="submit" class="btn btn--primary">Entrar</button>
      </div>
    </form>

  <?php else: ?>

    <div class="admin__head">
      <h1>Inscripciones <span class="hl">a los webinars</span></h1>
      <a class="btn btn--primary" href="?descargar=csv">Descargar para Excel</a>
    </div>

    <div class="stats">
      <?php foreach (SESIONES as $sesion => $titulo): ?>
        <a class="card stat s-<?= e($sesion) ?>" href="#sesion-<?= e($sesion) ?>">
          <span class="kicker kicker--light"><?= e(etiqueta($sesion)) ?></span>
          <span class="stat__num"><?= count($porSesion[$sesion]) ?></span>
          <span class="stat__label">inscritos reales</span>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="admin__text">En la web pública, el contador suma a estas cifras los inscritos de ejemplo (<code>INSCRITOS_BASE</code> en <code>app.js</code>).</p>

    <?php foreach (SESIONES as $sesion => $titulo): ?>
      <section class="card s-<?= e($sesion) ?>" id="sesion-<?= e($sesion) ?>">
        <div class="list-head">
          <h2><?= e($titulo) ?></h2>
          <span class="count"><?= count($porSesion[$sesion]) ?></span>
        </div>
        <?php if (!$porSesion[$sesion]): ?>
          <p class="empty">Todavía no hay inscripciones.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>Nº</th><th>Nombre y apellidos</th><th>Empresa</th><th>Cargo</th><th>Correo</th><th>Fecha</th></tr>
              </thead>
              <tbody>
                <?php foreach ($porSesion[$sesion] as $i => $f): ?>
                  <tr>
                    <td class="num"><?= $i + 1 ?></td>
                    <td><?= e($f['nombre']) ?></td>
                    <td><?= e($f['empresa']) ?></td>
                    <td><?= e($f['cargo']) ?></td>
                    <td><?= e($f['correo']) ?></td>
                    <td class="num"><?= e(fecha_legible($f['fecha'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

  <?php endif; ?>
  </main>
</body>
</html>
