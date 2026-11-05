<?php
/**
 * GET /api/contador.php
 * Devuelve solo cuántas inscripciones reales hay por sesión (ningún dato personal):
 * { ok: true, inscritos: { gestor: 3, poseedor: 0, scrap: 1 } }
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/inscripciones.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(['ok' => true, 'inscritos' => contar_inscripciones()]);
