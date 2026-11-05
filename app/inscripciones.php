<?php
/**
 * Inscripciones a los webinars: configuración, lectura y escritura del CSV.
 * Este archivo vive fuera de public/ y no se sirve nunca directamente.
 */
declare(strict_types=1);

date_default_timezone_set('Europe/Madrid');

const SESIONES = [
    'gestor'   => 'Sesión 1 · Gestores — jueves 19 de noviembre de 2026, 10:00 h',
    'poseedor' => 'Sesión 2 · Poseedores — jueves 10 de diciembre de 2026, 10:00 h',
    'scrap'    => 'Sesión 3 · SCRAP — jueves 17 de diciembre de 2026, 10:00 h',
];

const COLUMNAS = ['fecha', 'sesion', 'nombre', 'empresa', 'cargo', 'correo'];

const LONGITUD_MAX = 120;

/** Valor de configuración: primero variable de entorno, después el archivo .env de la raíz. */
function config(string $clave, string $defecto = ''): string
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $archivo = dirname(__DIR__) . '/.env';
        if (is_readable($archivo)) {
            foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
                $linea = trim($linea);
                if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $linea, 2));
                $vars[$k] = trim($v, "\"'");
            }
        }
    }
    $entorno = getenv($clave);
    if ($entorno !== false && $entorno !== '') {
        return $entorno;
    }
    return $vars[$clave] ?? $defecto;
}

function ruta_csv(): string
{
    return dirname(__DIR__) . '/data/inscripciones.csv';
}

/** Limpia un texto: sin caracteres de control y con los espacios normalizados. */
function limpiar(mixed $valor): string
{
    $texto = is_scalar($valor) ? (string) $valor : '';
    $texto = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $texto) ?? '';
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';
    return trim($texto);
}

/** @return list<array<string,string>> */
function leer_filas($fh): array
{
    rewind($fh);
    $filas = [];
    $cabecera = fgetcsv($fh, null, ',', '"', '');
    if (!$cabecera) {
        return [];
    }
    while (($fila = fgetcsv($fh, null, ',', '"', '')) !== false) {
        if (count($fila) !== count($cabecera)) {
            continue;
        }
        $filas[] = array_combine($cabecera, $fila);
    }
    return $filas;
}

/** @return list<array<string,string>> Todas las inscripciones, de la más antigua a la más reciente. */
function leer_inscripciones(): array
{
    $ruta = ruta_csv();
    if (!is_file($ruta)) {
        return [];
    }
    $fh = fopen($ruta, 'r');
    if (!$fh) {
        return [];
    }
    flock($fh, LOCK_SH);
    $filas = leer_filas($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $filas;
}

/** @return array<string,int> Número de inscripciones reales por sesión. */
function contar_inscripciones(): array
{
    $total = array_fill_keys(array_keys(SESIONES), 0);
    foreach (leer_inscripciones() as $fila) {
        if (isset($total[$fila['sesion']])) {
            $total[$fila['sesion']]++;
        }
    }
    return $total;
}

/**
 * Añade una inscripción si ese correo no está ya inscrito en la sesión.
 * El bloqueo exclusivo evita duplicados y escrituras cruzadas si llegan dos a la vez.
 *
 * @param array{sesion:string,nombre:string,empresa:string,cargo:string,correo:string} $datos
 * @return 'ok'|'duplicado'
 */
function guardar_inscripcion(array $datos): string
{
    $ruta = ruta_csv();
    $dir = dirname($ruta);
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('No se puede crear la carpeta de datos.');
    }

    $fh = fopen($ruta, 'c+');
    if (!$fh) {
        throw new RuntimeException('No se puede abrir el archivo de inscripciones.');
    }

    try {
        flock($fh, LOCK_EX);
        $filas = leer_filas($fh);

        $correo = strtolower($datos['correo']);
        foreach ($filas as $fila) {
            if ($fila['sesion'] === $datos['sesion'] && strtolower($fila['correo']) === $correo) {
                return 'duplicado';
            }
        }

        fseek($fh, 0, SEEK_END);
        if (ftell($fh) === 0) {
            fputcsv($fh, COLUMNAS, ',', '"', '');
        }
        $registro = ['fecha' => date('c')] + $datos;
        fputcsv($fh, array_map(fn ($c) => $registro[$c] ?? '', COLUMNAS), ',', '"', '');
        fflush($fh);
        return 'ok';
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/** Aviso opcional por correo a NOTIFY_EMAIL. Si el servidor no puede enviar correo, se ignora. */
function notificar_inscripcion(array $datos): void
{
    $destino = config('NOTIFY_EMAIL');
    if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $asunto = 'Nueva inscripción · ' . explode(' — ', SESIONES[$datos['sesion']])[0];
    $cuerpo = SESIONES[$datos['sesion']] . "\n\n"
        . "Nombre: {$datos['nombre']}\n"
        . "Empresa: {$datos['empresa']}\n"
        . "Cargo: {$datos['cargo']}\n"
        . "Correo: {$datos['correo']}\n";
    $cabeceras = "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($destino, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $cabeceras);
}
