<?php
/**
 * POST /api/inscribir.php
 * Cuerpo JSON: { sesion, nombre, empresa, cargo, correo, privacidad, web }
 * Respuestas: 200 {ok:true,total}  ·  409/422 {ok:false,errores:{campo:mensaje}}  ·  500 {ok:false}
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/inscripciones.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $estado, array $datos): never
{
    http_response_code($estado);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder(405, ['ok' => false]);
}

$entrada = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    $entrada = $_POST;
}

// Campo trampa: las personas no lo ven; si viene relleno, es un robot. Se le responde "ok" sin guardar nada.
if (limpiar($entrada['web'] ?? '') !== '') {
    responder(200, ['ok' => true]);
}

$sesion = limpiar($entrada['sesion'] ?? '');
$datos = [
    'sesion'  => $sesion,
    'nombre'  => limpiar($entrada['nombre'] ?? ''),
    'empresa' => limpiar($entrada['empresa'] ?? ''),
    'cargo'   => limpiar($entrada['cargo'] ?? ''),
    'correo'  => limpiar($entrada['correo'] ?? ''),
];

$errores = [];
if (!isset(SESIONES[$sesion])) {
    responder(422, ['ok' => false, 'errores' => ['sesion' => 'Sesión no válida.']]);
}
foreach (['nombre', 'empresa', 'cargo'] as $campo) {
    if ($datos[$campo] === '') {
        $errores[$campo] = 'Completa este campo.';
    } elseif (preg_match_all('/./su', $datos[$campo]) > LONGITUD_MAX) {
        $errores[$campo] = 'Máximo ' . LONGITUD_MAX . ' caracteres.';
    }
}
if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || strlen($datos['correo']) > 254) {
    $errores['correo'] = 'Escribe un correo electrónico válido.';
}
if (($entrada['privacidad'] ?? false) !== true && ($entrada['privacidad'] ?? '') !== 'on') {
    $errores['privacidad'] = 'Marca la casilla para confirmar que has leído la Política de Privacidad.';
}
if ($errores) {
    responder(422, ['ok' => false, 'errores' => $errores]);
}

try {
    if (guardar_inscripcion($datos) === 'duplicado') {
        responder(409, ['ok' => false, 'errores' => ['correo' => 'Este correo ya está inscrito en esta sesión.']]);
    }
} catch (Throwable $e) {
    error_log('Inscripción no guardada: ' . $e->getMessage());
    responder(500, ['ok' => false]);
}

notificar_inscripcion($datos);

responder(200, ['ok' => true, 'total' => contar_inscripciones()[$sesion]]);
