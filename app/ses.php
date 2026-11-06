<?php
/**
 * Envío por Amazon SES (API v2, región eu-west-1) sin SDK ni credenciales:
 * usa el rol IAM de la instancia EC2 (credenciales temporales de IMDSv2) y firma SigV4.
 * Si falla (sin rol, sin red, SES devuelve error) devuelve false y enviar_correo() hace fallback a mail().
 */
declare(strict_types=1);

const SES_REGION = 'eu-west-1';

/** Credenciales temporales del rol de la instancia (IMDSv2). Cache en proceso. */
function ses_credenciales(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache ?: null;
    }
    $cache = false;
    $imds = function (string $ruta, array $cabeceras, string $metodo = 'GET'): ?string {
        $ch = curl_init('http://169.254.169.254' . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $cabeceras,
        ]);
        $r = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ($r !== false && $codigo === 200) ? $r : null;
    };
    $token = $imds('/latest/api/token', ['X-aws-ec2-metadata-token-ttl-seconds: 300'], 'PUT');
    if ($token === null) {
        return null;
    }
    $auth = ['X-aws-ec2-metadata-token: ' . $token];
    $rol = trim((string) $imds('/latest/meta-data/iam/security-credentials/', $auth));
    if ($rol === '') {
        return null;
    }
    $json = json_decode((string) $imds('/latest/meta-data/iam/security-credentials/' . $rol, $auth), true);
    if (!is_array($json) || ($json['Code'] ?? '') !== 'Success') {
        return null;
    }
    $cache = ['key' => $json['AccessKeyId'], 'secret' => $json['SecretAccessKey'], 'token' => $json['Token']];
    return $cache;
}

/** Envía un mensaje MIME ya construido a través de SES. Devuelve true si SES lo acepta. */
function ses_enviar_raw(string $de, string $para, string $mime): bool
{
    $cred = ses_credenciales();
    if ($cred === null) {
        return false;
    }
    $host = 'email.' . SES_REGION . '.amazonaws.com';
    $ruta = '/v2/email/outbound-emails';
    $cuerpo = json_encode([
        'FromEmailAddress' => $de,
        'Destination' => ['ToAddresses' => [$para]],
        'Content' => ['Raw' => ['Data' => base64_encode($mime)]],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ahora = gmdate('Ymd\THis\Z');
    $fecha = substr($ahora, 0, 8);
    $hashCuerpo = hash('sha256', $cuerpo);
    $cabecerasFirma = [
        'content-type' => 'application/json',
        'host' => $host,
        'x-amz-content-sha256' => $hashCuerpo,
        'x-amz-date' => $ahora,
        'x-amz-security-token' => $cred['token'],
    ];
    ksort($cabecerasFirma);
    $cabecerasCanon = '';
    foreach ($cabecerasFirma as $k => $v) {
        $cabecerasCanon .= $k . ':' . trim($v) . "\n";
    }
    $firmadas = implode(';', array_keys($cabecerasFirma));
    $peticionCanon = "POST\n{$ruta}\n\n{$cabecerasCanon}\n{$firmadas}\n{$hashCuerpo}";
    $ambito = "{$fecha}/" . SES_REGION . "/ses/aws4_request";
    $aFirmar = "AWS4-HMAC-SHA256\n{$ahora}\n{$ambito}\n" . hash('sha256', $peticionCanon);
    $kFecha = hash_hmac('sha256', $fecha, 'AWS4' . $cred['secret'], true);
    $kRegion = hash_hmac('sha256', SES_REGION, $kFecha, true);
    $kServicio = hash_hmac('sha256', 'ses', $kRegion, true);
    $kFirma = hash_hmac('sha256', 'aws4_request', $kServicio, true);
    $firma = hash_hmac('sha256', $aFirmar, $kFirma);
    $autorizacion = "AWS4-HMAC-SHA256 Credential={$cred['key']}/{$ambito}, SignedHeaders={$firmadas}, Signature={$firma}";

    $ch = curl_init('https://' . $host . $ruta);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $cuerpo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Amz-Content-Sha256: ' . $hashCuerpo,
            'X-Amz-Date: ' . $ahora,
            'X-Amz-Security-Token: ' . $cred['token'],
            'Authorization: ' . $autorizacion,
        ],
    ]);
    $respuesta = (string) curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($codigo !== 200) {
        error_log("SES rechazo el correo a {$para} (HTTP {$codigo}): " . substr($respuesta, 0, 300));
        return false;
    }
    return true;
}
