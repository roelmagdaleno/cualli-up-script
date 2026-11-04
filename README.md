# Cualli - Link Monitor

Mu-plugin de WordPress que cada **lunes a las 3:00 AM (hora de la Ciudad de México)** escanea cualli.mx y envía un correo si encuentra recursos rotos:

- Enlaces internos (`<a href>` del mismo dominio)
- Imágenes (`<img src>`, `srcset`, `<source srcset>`)
- Hojas de estilo (`<link rel="stylesheet">`) y scripts (`<script src>`) internos

Los enlaces externos no se revisan. Si no hay errores, no se envía nada.

## Instalación

Copia estos dos elementos a `wp-content/mu-plugins/`:

```
cualli-link-monitor.php      ← cargador (WordPress solo carga archivos en la raíz de mu-plugins)
cualli-link-monitor/         ← código
```

No hay que activar nada. En la siguiente petición al sitio, el plugin programa el evento semanal.

Agrega la API key de Resend a `wp-config.php` (usa una key con permiso `sending_access`):

```php
define( 'CUALLI_LINK_MONITOR_RESEND_API_KEY', 're_...' );
```

Sin esta constante el reporte no se envía: el error queda en el log de PHP y `wp cualli-links scan` lo muestra.

**Remitente:** el default, `onboarding@resend.dev`, no requiere verificar un dominio, pero Resend lo describe como de prueba y solo entrega al correo de la cuenta de Resend. Para otros destinatarios, verifica en Resend un dominio con acceso a su DNS y cambia `cualli_link_monitor_email_from`.

**Recomendado en Kinsta:** pedir a soporte que active el cron del servidor (cada 5 o 15 minutos). WP-Cron solo corre cuando alguien visita el sitio, y a las 3 AM puede haber pocas visitas. Sin cron del servidor, el escaneo empieza con la primera visita después de esa hora.

## Comandos WP-CLI

```bash
wp cualli-links scan --no-email          # Escanea ahora y muestra el reporte, sin enviar correo
wp cualli-links scan --notify-when-clean # Escanea y envía el correo aunque no haya errores
wp cualli-links status                   # Próximo escaneo y resumen del último
wp cualli-links reschedule               # Vuelve a programar el evento semanal
```

`wp cron event run cualli_link_monitor_weekly_scan` recorre el evento semanal a la hora en que se ejecuta. Después de usarlo, corre `wp cualli-links reschedule`.

## Configuración (filtros)

No hay pantalla de ajustes. Cada valor tiene un default en `src/Config.php` y un filtro:

| Filtro | Default |
|---|---|
| `cualli_link_monitor_recipients` | `['roelmagdaleno@gmail.com']` |
| `cualli_link_monitor_notify_when_clean` | `false` |
| `cualli_link_monitor_email_from` | `'Cualli Link Monitor <onboarding@resend.dev>'` |
| `cualli_link_monitor_resend_api_key` | Valor de la constante `CUALLI_LINK_MONITOR_RESEND_API_KEY` |
| `cualli_link_monitor_scan_weekday` | `'monday'` |
| `cualli_link_monitor_scan_time` | `'03:00'` (zona horaria del sitio) |
| `cualli_link_monitor_batch_time_budget` | `120` segundos por lote |
| `cualli_link_monitor_request_timeout` | `15` segundos por petición |
| `cualli_link_monitor_post_types` | `['page', 'post']` |
| `cualli_link_monitor_taxonomies` | `['category', 'post_tag']` |
| `cualli_link_monitor_excluded_paths` | `/wp-admin`, `/wp-login.php`, `/wp-json`, `/xmlrpc.php`, `/feed`, `/cdn-cgi`, `/author` (bloqueado por seguridad, siempre da 404) y el slug de WPS Hide Login |

Ejemplo, para cambiar el destinatario desde otro mu-plugin:

```php
add_filter( 'cualli_link_monitor_recipients', fn () => array( 'web@cualli.mx' ) );
```

Si cambias el día o la hora, corre `wp cualli-links reschedule`.

## Cómo funciona

1. **Lunes 3:00 AM:** el evento `cualli_link_monitor_weekly_scan` arma la lista de páginas con funciones de WordPress: home, entradas publicadas sin contraseña y archivos de términos.
2. **Lotes:** cada lote descarga páginas hasta agotar su tiempo (120 s). Revisa cada URL interna una sola vez con `HEAD` (o `GET` parcial si el servidor no acepta `HEAD`) y guarda el avance en la opción `cualli_link_monitor_scan_state`.
3. **Siguiente lote:** si quedan páginas, programa `cualli_link_monitor_process_batch`. Si no, guarda el resumen en `cualli_link_monitor_last_summary` y envía el correo con la API de Resend.

Un timeout o un error 5xx se reintenta una vez después de 3 s, para evitar falsas alarmas por fallas momentáneas. Una URL se considera rota con un código 4xx/5xx o sin respuesta.

### Estructura (PSR-4, `Cualli\LinkMonitor\` → `cualli-link-monitor/src/`)

```
Plugin.php                 Registra hooks de WordPress y el comando WP-CLI
ServiceFactory.php         Único lugar donde se construyen y conectan las clases
Config.php                 Defaults y filtros
Autoloader.php             Autoloader PSR-4 (el servidor no tiene Composer)
Schedule/WeeklyScheduler   Programa el evento semanal
Discovery/PageUrlCollector Lista de páginas a escanear
Html/ReferenceExtractor    Extrae URLs del HTML (reglas XPath en RULES)
Html/UrlNormalizer         Resuelve URLs relativas y descarta mailto:, tel:, #…
Html/InternalUrlPolicy     Solo URLs del sitio y fuera de rutas excluidas
Http/HttpClient            Peticiones HTTP con reintento
Scan/ScanRunner            Orquesta el escaneo (cron por lotes o WP-CLI de una vez)
Scan/PageScanner           Escanea una página
Scan/ScanState             Avance del escaneo (solo arrays, se guarda en una opción)
Scan/ScanStateRepository   Lee y guarda estado y resumen
Scan/BatchDispatcher       Programa el siguiente lote
Report/ReportBuilder       Texto del correo
Report/ReportMailer        Decide si se envía el correo y registra los fallos
Report/ResendClient        Envía el correo con la API de Resend
Cli/LinkMonitorCommand     wp cualli-links
Model/*                    Objetos de valor (Reference, ReferenceType, CheckResult…)
```

Para revisar un tipo de elemento nuevo (por ejemplo `<video src>`), agrega una regla a `ReferenceExtractor::RULES` y, si hace falta, un caso a `ReferenceType`.

## Desarrollo

```bash
composer install
composer lint       # PHPCS con WordPress Coding Standards
composer lint:fix   # Corrige lo que se pueda automáticamente
```

Reglas del código: WPCS, PSR-4, una responsabilidad por clase, sin `else`/`elseif` (usar retornos tempranos) y sin condiciones anidadas.
