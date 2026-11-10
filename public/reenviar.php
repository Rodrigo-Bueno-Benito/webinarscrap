<?php
/**
 * Reenvío del correo de confirmación a personas ya inscritas.
 * Pensado para quienes se apuntaron antes de que el envío de correo funcionara.
 * Usa la misma sesión que el panel (inscritos.php): hay que haber entrado allí primero.
 * Cada envío queda anotado en data/reenvios.csv para no repetirlo por error.
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

if (config('ADMIN_PASSWORD') === '' || empty($_SESSION['admin'])) {
    header('Location: inscritos.php', true, 303);
    exit;
}

/** Por defecto se marcan las inscripciones anteriores a este momento (hora de Madrid). */
const CORTE_DEFECTO = '2026-11-10T13:59';

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ruta_reenvios(): string
{
    return dirname(__DIR__) . '/data/reenvios.csv';
}

/** Identifica una inscripción: sesión + correo (la app no admite duplicados de ese par). */
function clave_fila(array $fila): string
{
    return $fila['sesion'] . '|' . strtolower($fila['correo']);
}

/** @return array<string,string> clave de inscripción => fecha del último reenvío correcto */
function reenvios_hechos(): array
{
    $ruta = ruta_reenvios();
    $hechos = [];
    if (!is_file($ruta) || !($fh = fopen($ruta, 'r'))) {
        return $hechos;
    }
    flock($fh, LOCK_SH);
    while (($l = fgetcsv($fh, null, ',', '"', '')) !== false) {
        if (count($l) >= 4 && $l[3] === 'ok') {
            $hechos[$l[1] . '|' . strtolower($l[2])] = $l[0];
        }
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $hechos;
}

function anotar_reenvio(array $fila, bool $ok): void
{
    $fh = fopen(ruta_reenvios(), 'a');
    if (!$fh) {
        return;
    }
    flock($fh, LOCK_EX);
    fputcsv($fh, [date('c'), $fila['sesion'], $fila['correo'], $ok ? 'ok' : 'fallo'], ',', '"', '');
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$corte = (string) ($_REQUEST['hasta'] ?? CORTE_DEFECTO);
if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/', $corte) || strtotime($corte) === false) {
    $corte = CORTE_DEFECTO;
}
$tCorte = strtotime($corte);

$filas = array_values(array_filter(leer_inscripciones(), fn ($f) => isset(SESIONES[$f['sesion']])));
$hechos = reenvios_hechos();
$resultados = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reenviar') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'La página había caducado. Vuelve a marcar las filas y pulsa de nuevo.';
    } else {
        $elegidas = array_flip(array_map('strval', (array) ($_POST['fila'] ?? [])));
        $forzar = !empty($_POST['forzar']);
        $resultados = [];
        set_time_limit(600);
        foreach ($filas as $f) {
            $k = clave_fila($f);
            if (!isset($elegidas[$k])) {
                continue;
            }
            if (isset($hechos[$k]) && !$forzar) {
                $resultados[] = [$f, 'ya'];
                continue;
            }
            $ok = enviar_confirmacion($f);
            anotar_reenvio($f, $ok);
            if ($ok) {
                $hechos[$k] = date('c');
            }
            $resultados[] = [$f, $ok ? 'ok' : 'fallo'];
            usleep(150000);   // muy por debajo del límite de envío de SES
        }
        if (!$resultados) {
            $error = 'No has marcado ninguna inscripción.';
            $resultados = null;
        }
    }
}

$textoResultado = ['ok' => 'Enviado', 'fallo' => 'NO enviado', 'ya' => 'Ya reenviado antes (omitido)'];
$pendientes = 0;
foreach ($filas as $f) {
    $t = strtotime($f['fecha']);
    if ($t !== false && $t < $tCorte && !isset($hechos[clave_fila($f)])) {
        $pendientes++;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Reenviar confirmación · Webinars SCRAPenvases.com</title>
  <link rel="icon" href="favicon.ico">
  <link rel="stylesheet" href="styles.css?v=<?= filemtime(__DIR__ . '/styles.css') ?>">
</head>
<body>
  <header class="nav">
    <div class="wrap nav__in">
      <a class="nav__logo" href="./" aria-label="Volver a los webinars">
        <img src="img/logo-scrapenvases.png" alt="SCRAPenvases.com" width="760" height="59">
      </a>
      <span class="nav__title">Reenviar confirmación</span>
      <a class="btn btn--ghost btn--sm" href="inscritos.php">Volver al panel</a>
    </div>
  </header>

  <main class="wrap admin">
    <div class="admin__head">
      <h1>Reenviar <span class="hl">la confirmación</span></h1>
    </div>
    <p class="admin__text">Envía de nuevo el correo «Gracias por inscribirse…» a las inscripciones que marques. Vienen marcadas las anteriores a la fecha de corte que todavía no se han reenviado (<strong><?= $pendientes ?></strong>). Cada envío se anota y no se repite salvo que lo fuerces.</p>

    <?php if ($error !== ''): ?>
      <p class="form-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($resultados !== null): ?>
      <section class="card">
        <div class="list-head"><h2>Resultado del envío</h2><span class="count"><?= count($resultados) ?></span></div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Sesión</th><th>Nombre y apellidos</th><th>Correo</th><th>Resultado</th></tr></thead>
            <tbody>
              <?php foreach ($resultados as [$f, $r]): ?>
                <tr>
                  <td><?= e(SESIONES[$f['sesion']]['sesion']) ?></td>
                  <td><?= e($f['nombre']) ?></td>
                  <td><?= e($f['correo']) ?></td>
                  <td><?= $r === 'fallo' ? '<strong>' . e($textoResultado[$r]) . '</strong>' : e($textoResultado[$r]) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <form method="get" class="card">
      <div class="field">
        <label for="hasta">Marcar las inscripciones anteriores a (hora de Madrid)</label>
        <input id="hasta" name="hasta" type="datetime-local" value="<?= e($corte) ?>" required>
      </div>
      <div class="actions">
        <button type="submit" class="btn btn--ghost btn--sm">Aplicar fecha de corte</button>
      </div>
    </form>

    <form method="post" class="card" onsubmit="return confirm('Se enviará un correo real a cada inscripción marcada. ¿Continuar?');">
      <input type="hidden" name="accion" value="reenviar">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="hasta" value="<?= e($corte) ?>">
      <div class="list-head"><h2>Inscripciones</h2><span class="count"><?= count($filas) ?></span></div>
      <?php if (!$filas): ?>
        <p class="empty">Todavía no hay inscripciones.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Reenviar</th><th>Sesión</th><th>Nombre y apellidos</th><th>Correo</th><th>Fecha de inscripción</th><th>Reenviado</th></tr></thead>
            <tbody>
              <?php foreach ($filas as $f):
                  $k = clave_fila($f);
                  $t = strtotime($f['fecha']);
                  $hecho = $hechos[$k] ?? '';
                  $marcar = $t !== false && $t < $tCorte && $hecho === '';
              ?>
                <tr>
                  <td><input type="checkbox" name="fila[]" value="<?= e($k) ?>"<?= $marcar ? ' checked' : '' ?> aria-label="Reenviar a <?= e($f['correo']) ?>"></td>
                  <td><?= e(SESIONES[$f['sesion']]['sesion']) ?></td>
                  <td><?= e($f['nombre']) ?></td>
                  <td><?= e($f['correo']) ?></td>
                  <td class="num"><?= e($t ? date('d/m/Y H:i', $t) : $f['fecha']) ?></td>
                  <td class="num"><?= $hecho !== '' ? e(date('d/m/Y H:i', (int) strtotime($hecho))) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="admin__text"><label><input type="checkbox" name="forzar" value="1"> Enviar también a quienes ya figuran como reenviados</label></p>
        <div class="actions">
          <button type="submit" class="btn btn--primary">Reenviar a las marcadas</button>
        </div>
      <?php endif; ?>
    </form>
  </main>
</body>
</html>
