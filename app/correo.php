<?php
/**
 * Correos de las inscripciones:
 *  - Confirmación a la persona que se inscribe ("Gracias por inscribirse…").
 *  - Aviso interno opcional a NOTIFY_EMAIL.
 * Se envían por Amazon SES con el rol IAM de la instancia (app/ses.php); si SES no está
 * disponible se recurre a mail() de PHP. Remitente y web se configuran en .env (ver .env.example).
 */
declare(strict_types=1);

require_once __DIR__ . '/ses.php';

/** Textos legales de Inpronet Solutions, S.L. para el pie de los correos. */
const AVISO_CONFIDENCIALIDAD = 'Este mensaje, remitido por Inpronet Solutions, S.L., contiene información confidencial dirigida exclusivamente a su destinatario. Si usted no lo es, le rogamos lo notifique de inmediato y lo elimine, quedando prohibida su reproducción o uso indebido.';
const PROTECCION_DATOS = [
    'Razón social'  => 'Inpronet Solutions, S.L.',
    'Domicilio'     => 'Bulevar Manzanares 6. 28701, San Sebastián de los Reyes (Madrid), España',
    'DPO'           => 'rgpd@auratechlegal.es',
    'Destinatarios' => 'No se cederán datos a terceros, salvo obligación legal.',
    'Finalidad'     => 'Gestionar nuestras comunicaciones profesionales y operativas, así como responder a solicitudes, coordinar servicios o mantener contacto institucional o contractual con clientes, proveedores y colaboradores.',
    'Procedencia'   => 'Su dirección de email ha sido recabada del propio interesado y la utilizaremos para mantener contacto con usted.',
    'Legitimación'  => 'Art. 6.1.b) y f) del RGPD – ejecución de un contrato y satisfacción del interés legítimo del responsable.',
    'Derechos'      => 'Puede acceder, rectificar y suprimir sus datos, portabilidad de los datos, limitación u oposición a su tratamiento, derecho a no ser objeto de decisiones automatizadas, así como a obtener información clara y transparente sobre el tratamiento de sus datos escribiendo a rgpd@auratechlegal.es o a nuestra web http://www.inpsolutions.com. Si considera que el tratamiento no se ajusta a la normativa vigente de Protección de Datos, podrá presentar una reclamación ante la autoridad de control: Agencia Española de Protección de Datos (https://www.aepd.es)',
];

/** Escapa un texto para HTML y convierte en enlaces sus correos y direcciones web. */
function html_con_enlaces(string $texto, string $estilo): string
{
    $seguro = htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return preg_replace_callback(
        '~https?://[\w./-]*\w|[\w.+-]+@[\w-]+(?:\.[\w-]+)+~',
        fn (array $m): string => '<a href="' . (str_contains($m[0], '@') ? 'mailto:' : '') . $m[0] . "\" style=\"{$estilo}\">{$m[0]}</a>",
        $seguro
    );
}

function correo_remitente(): string
{
    $de = config('MAIL_FROM', 'ots@scrapenvases.com');
    return filter_var($de, FILTER_VALIDATE_EMAIL) ? $de : '';
}

function url_web(): string
{
    return rtrim(config('SITE_URL', 'https://webinar.scrapenvases.com'), '/');
}

/**
 * Asunto, texto plano y HTML del correo de confirmación.
 * @param array{sesion:string,nombre:string} $datos
 * @return array{asunto:string,texto:string,html:string}
 */
function correo_confirmacion(array $datos): array
{
    $s = SESIONES[$datos['sesion']];
    $web = url_web();
    $contacto = correo_remitente() ?: 'ots@scrapenvases.com';
    $privacidad = $web . '/politica-privacidad.html';

    $asunto = "Gracias por inscribirse en el {$s['titulo']} de SCRAPenvases.com";

    $texto = "Hola, {$datos['nombre']}:\n\n"
        . "Gracias por inscribirse en el {$s['titulo']} de SCRAPenvases.com.\n\n"
        . "{$s['sesion']} — «{$s['lema']}»\n"
        . "{$s['fecha']}\n\n"
        . "Próximamente recibirá el enlace al webinar en esta dirección de correo electrónico.\n\n"
        . "Si tiene cualquier duda, escríbanos a {$contacto}.\n\n"
        . "Un saludo,\n"
        . "El equipo de SCRAPenvases.com\n\n"
        . "--\n"
        . "Ha recibido este correo porque se ha inscrito en un webinar de SCRAPenvases.com. "
        . "Responsable del tratamiento: Inpronet Solutions, S.L. Más información en nuestra Política de Privacidad: {$privacidad}\n\n"
        . AVISO_CONFIDENCIALIDAD . "\n\n"
        . "Información básica sobre protección de datos:\n";
    foreach (PROTECCION_DATOS as $etiqueta => $valor) {
        $texto .= "{$etiqueta}: {$valor}\n";
    }

    $h = fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $color = $s['color'];
    $fuente = "font-family:Arial,Helvetica,sans-serif;";
    $enlaceLegal = 'color:#8a929a;';
    $datosHtml = '';
    foreach (PROTECCION_DATOS as $etiqueta => $valor) {
        $datosHtml .= "<br><strong>{$h($etiqueta)}:</strong> " . html_con_enlaces($valor, $enlaceLegal);
    }
    $avisoHtml = $h(AVISO_CONFIDENCIALIDAD);

    $html = <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$h($asunto)}</title>
</head>
<body style="margin:0;padding:0;background:#f3f5f7;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$h($s['fecha'])}. Próximamente recibirá el enlace al webinar.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f5f7;">
  <tr>
    <td align="center" style="padding:32px 16px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr>
          <td style="background:#07090c;">
            <img src="{$h($web)}/img/banner-inscripcion.jpg" width="600" alt="SCRAPenvases.com · Ciclo de webinars · Gracias por tu inscripción" style="display:block;width:100%;max-width:600px;height:auto;border:0;{$fuente}color:#ffffff;font-size:18px;font-weight:bold;">
          </td>
        </tr>
        <tr><td style="height:4px;line-height:4px;font-size:0;background:{$color};">&nbsp;</td></tr>
        <tr>
          <td style="padding:36px 36px 12px;{$fuente}color:#0b0c0e;">
            <p style="margin:0 0 8px;font-size:12px;line-height:1.4;letter-spacing:1px;text-transform:uppercase;font-weight:bold;color:{$color};">{$h($s['sesion'])}</p>
            <h1 style="margin:0 0 18px;font-size:26px;line-height:1.2;font-weight:bold;color:#0b0c0e;">Gracias por inscribirse, {$h($datos['nombre'])}</h1>
            <p style="margin:0 0 22px;font-size:16px;line-height:1.6;color:#464e56;">Su inscripción en el <strong style="color:#0b0c0e;">{$h($s['titulo'])}</strong> de SCRAPenvases.com se ha registrado correctamente.</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f5f7;border-radius:12px;border-left:4px solid {$color};">
              <tr>
                <td style="padding:18px 22px;{$fuente}">
                  <p style="margin:0 0 6px;font-size:19px;line-height:1.35;font-style:italic;color:#0b0c0e;">«{$h($s['lema'])}»</p>
                  <p style="margin:0;font-size:15px;line-height:1.5;font-weight:bold;color:#0b0c0e;">{$h($s['fecha'])}</p>
                </td>
              </tr>
            </table>
            <p style="margin:24px 0 16px;font-size:16px;line-height:1.6;color:#464e56;"><strong style="color:#0b0c0e;">Próximamente recibirá el enlace al webinar</strong> en esta dirección de correo electrónico.</p>
            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:#464e56;">Si tiene cualquier duda, escríbanos a <a href="mailto:{$h($contacto)}" style="color:#0b0c0e;">{$h($contacto)}</a>.</p>
            <p style="margin:0 0 28px;font-size:16px;line-height:1.6;color:#464e56;">Un saludo,<br>El equipo de SCRAPenvases.com</p>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 36px 14px;border-top:1px solid #e9edf0;{$fuente}font-size:12px;line-height:1.6;color:#69727b;">
            Ha recibido este correo porque se ha inscrito en un webinar de SCRAPenvases.com. Responsable del tratamiento: Inpronet Solutions, S.L. Más información en nuestra <a href="{$h($privacidad)}" style="color:#69727b;">Política de Privacidad</a>.
          </td>
        </tr>
        <tr>
          <td style="padding:0 36px 32px;{$fuente}font-size:11px;line-height:1.55;color:#8a929a;">
            <p style="margin:0 0 10px;">{$avisoHtml}</p>
            <p style="margin:0;"><strong>Información básica sobre protección de datos</strong>{$datosHtml}</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

    return ['asunto' => $asunto, 'texto' => $texto, 'html' => $html];
}

/** Codifica un texto con tildes para una cabecera de correo (asunto, nombre del remitente). */
function cabecera_utf8(string $texto): string
{
    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

/**
 * Envía un correo en texto plano y, si se da, también en HTML.
 * Devuelve false si no hay remitente válido o si el servidor no lo acepta.
 */
function enviar_correo(string $para, string $asunto, string $texto, ?string $html = null): bool
{
    $de = correo_remitente();
    if ($de === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $dominio = substr(strrchr($de, '@'), 1);
    $cabeceras = [
        'From: ' . cabecera_utf8(config('MAIL_FROM_NAME', 'SCRAPenvases.com')) . " <{$de}>",
        "Reply-To: {$de}",
        'MIME-Version: 1.0',
        'Message-ID: <' . bin2hex(random_bytes(16)) . "@{$dominio}>",
    ];

    if ($html === null) {
        $cabeceras[] = 'Content-Type: text/plain; charset=UTF-8';
        $cabeceras[] = 'Content-Transfer-Encoding: base64';
        $cuerpo = chunk_split(base64_encode($texto));
    } else {
        $limite = 'b' . bin2hex(random_bytes(12));
        $cabeceras[] = "Content-Type: multipart/alternative; boundary=\"{$limite}\"";
        $cuerpo = "--{$limite}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texto))
            . "--{$limite}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--{$limite}--\r\n";
    }

    // 1) Amazon SES con el rol IAM de la instancia (firma DKIM del dominio, pasa DMARC).
    $mime = "To: {$para}\r\nSubject: " . cabecera_utf8($asunto) . "\r\n" . implode("\r\n", $cabeceras) . "\r\n\r\n" . $cuerpo;
    if (ses_enviar_raw($de, $para, $mime)) {
        return true;
    }
    // 2) Respaldo: mail() de PHP. -f: el remitente del sobre coincide con el From.
    $ok = @mail($para, cabecera_utf8($asunto), $cuerpo, implode("\r\n", $cabeceras), '-f' . $de);
    if (!$ok) {
        error_log("Correo no enviado a {$para}: {$asunto}");
    }
    return $ok;
}

/** Correo "Gracias por inscribirse…" a la persona inscrita. */
function enviar_confirmacion(array $datos): bool
{
    $correo = correo_confirmacion($datos);
    return enviar_correo($datos['correo'], $correo['asunto'], $correo['texto'], $correo['html']);
}

/** Aviso interno opcional a NOTIFY_EMAIL con los datos de la inscripción. */
function notificar_inscripcion(array $datos): void
{
    $destino = config('NOTIFY_EMAIL');
    if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $texto = nombre_sesion($datos['sesion']) . "\n\n"
        . "Nombre: {$datos['nombre']}\n"
        . "Empresa: {$datos['empresa']}\n"
        . "Cargo: {$datos['cargo']}\n"
        . "Correo: {$datos['correo']}\n";
    enviar_correo($destino, 'Nueva inscripción · ' . SESIONES[$datos['sesion']]['sesion'], $texto);
}
