# Webinars · SCRAPenvases.com

Inscripción a las tres sesiones de webinars, con el estilo de scrapenvases.com:

| Pestaña | Sesión | Fecha |
|---|---|---|
| Webinar Gestor | Sesión 1 · Gestores — *El gestor que habla 27 idiomas* | Jueves 19 nov 2026 · 10:00 |
| Webinar Poseedor | Sesión 2 · Poseedores — *El incentivo que no llega* | Jueves 10 dic 2026 · 10:00 |
| Webinar Scrap | Sesión 3 · SCRAP — *El dato que no llega a tu plataforma* | Jueves 17 dic 2026 · 10:00 |

Cada formulario pide nombre y apellidos, empresa, cargo y correo, más la aceptación de la
política de privacidad. PHP 8.1 o superior, sin base de datos ni dependencias.

## Estructura

```
public/            raíz web (lo único que debe servir el servidor)
  index.html       los tres formularios
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
- `NOTIFY_EMAIL` (opcional): correo que recibe un aviso con cada inscripción. Requiere que
  el servidor tenga configurado `mail()` de PHP.

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
