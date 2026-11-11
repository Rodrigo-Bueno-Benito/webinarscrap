# Webinars · SCRAPenvases.com

Inscripción a las tres sesiones de webinars, con el estilo de scrapenvases.com:

| Pestaña | Sesión | Fecha |
|---|---|---|
| Webinar Gestor | Sesión 1 · Gestores — *El gestor que habla 27 idiomas* | Jueves 19 nov 2026 · 10:00 |
| Webinar Poseedor | Sesión 2 · Poseedores — *El incentivo que no llega* | Jueves 10 dic 2026 · 10:00 |
| Webinar Scrap | Sesión 3 · SCRAP — *El dato que no llega a tu plataforma* | Jueves 17 dic 2026 · 10:00 |

Cada formulario pide nombre y apellidos, empresa, cargo y correo, más la casilla (no premarcada)
«He leído la Política de Privacidad y declaro haber sido informado/a del tratamiento de mis datos
personales», enlazada a la página `public/politica-privacidad.html` (responsable: Inpronet
Solutions, S.L.). PHP 8.1 o superior, sin base de datos ni dependencias.

## Privacidad

- La política la facilita el asesor legal (Auratech). El PDF original está en `docs/` (no se
  publica) y su texto íntegro está en `public/politica-privacidad.html`. Si cambia, actualiza
  esa página y `POLITICA_VERSION` en `app/inscripciones.php`: cada inscripción guarda la
  versión que se le mostró.
- Los datos solo sirven para gestionar la inscripción, enviar el enlace y celebrar el webinar.
  Para usarlos en newsletters o comunicaciones comerciales hace falta otra casilla aparte.
- Según la política, se conservan solo el tiempo necesario para gestionar la inscripción y
  celebrar el webinar; después hay que suprimirlos (borrar `data/inscripciones.csv`).

## Estructura

```
public/            raíz web (lo único que debe servir el servidor)
  index.html       los tres formularios
  politica-privacidad.html  Política de Privacidad de los webinars
  app.js           pestañas, envío de inscripciones y contador
  styles.css       estilos
  inscritos.php    panel privado: inscritos por sesión y descarga para Excel
  api/inscribir.php  guarda una inscripción (POST)
  api/contador.php   número de inscritos por sesión (sin datos personales)
app/inscripciones.php  lectura y escritura del CSV, configuración
data/inscripciones.csv las inscripciones (se crea sola; fuera de git)
.env               configuración (fuera de git); ver .env.example
```

## Arrancar en local

```bash
php -S 127.0.0.1:5173 -t public
```

Formularios en <http://localhost:5173> y panel en <http://localhost:5173/inscritos.php>.

## Configuración (`.env`)

Copia `.env.example` a `.env` en la raíz del proyecto:

- `ADMIN_PASSWORD`: contraseña del panel `inscritos.php`. Vacía = panel desactivado.
- `MAIL_FROM` / `MAIL_FROM_NAME`: remitente del correo de confirmación (por defecto
  `ots@scrapenvases.com` / `SCRAPenvases.com`).
- `SITE_URL`: dirección pública de la web, para el logo y el enlace a la política del correo.
- `NOTIFY_EMAIL` (opcional): correo que recibe un aviso con cada inscripción.

## Correo de confirmación

Al inscribirse, cada persona recibe «Gracias por inscribirse en el Webinar …», con su sesión,
el lema y la fecha, y el aviso «Próximamente recibirá el enlace al webinar en esta dirección de
correo electrónico». El texto está en `app/correo.php` y se puede ver tal cual desde el panel
(`inscritos.php`, enlaces «Correo de confirmación»). La cabecera es el banner
`public/img/banner-inscripcion.jpg` (se carga desde `SITE_URL`) y el pie lleva los textos
legales de Inpronet (`AVISO_CONFIDENCIALIDAD` y `PROTECCION_DATOS` en `app/correo.php`).

Se envía con `mail()` de PHP, así que el servidor tiene que poder enviar correo. Para que no
acabe en spam, el dominio del remitente (`MAIL_FROM`) debe autorizar a ese servidor en su
registro SPF. Si el correo no sale, la inscripción se guarda igualmente.

## Ver cuánta gente se inscribe

Entra en `/inscritos.php` con la contraseña: verás el total de cada sesión, la lista de
inscritos y el botón **Descargar para Excel** (CSV con `;`, listo para abrir en Excel).

El contador de la web pública muestra los inscritos reales **más** unos inscritos de ejemplo,
definidos en `INSCRITOS_BASE` al principio de `public/app.js`. Ponlos a `0` para mostrar solo
los reales.

## Publicar

1. Sube todo el proyecto al servidor y apunta la raíz web a la carpeta **`public/`**
   (así `.env`, `app/` y `data/` quedan fuera del alcance de la web).
2. Crea `.env` con la contraseña del panel.
3. Asegúrate de que PHP puede escribir en la carpeta `data/`.

Caché: `public/.htaccess` hace que el navegador compruebe siempre si hay versión nueva de
HTML, CSS y JS. Aun así, si cambias `styles.css` o `app.js`, sube el número `?v=` con el que
los enlazan `index.html` y `politica-privacidad.html` (el panel lo calcula solo), para que
quien ya tenga la versión vieja guardada cargue la nueva.
