# All True Detection

Sitio público y portal de servicios. PHP 8.3+, Laravel 13, Blade, Fortify, Socialite (Google), Sanctum y API versionada. Referencia: [Vamo al Game](https://github.com/JorgeTonos28/Vamo_al_game). Sin Auth0 ni aplicación móvil paralela.

## Preparación

1. `composer install`.
2. Copiar `.env.example` a `.env` y configurar base de datos, `APP_URL`, correo y clave (`php artisan key:generate` solo en una instalación nueva).
3. `php artisan migrate --seed`. Los seeders preservan la configuración existente y crean el catálogo de once servicios; no crean contraseñas ni administradores predecibles.
4. `php artisan storage:link` para logos públicos. Expedientes y resultados permanecen en `storage/app/private` y se descargan mediante autorización.
5. `php artisan portal:create-admin correo@empresa.com`. Envía recuperación al correo indicado; si existe la cuenta, cambia su rol, rota la contraseña y revoca sesiones/tokens. El administrador verifica su correo y configura 2FA antes de operar.
6. `php artisan serve`; para correo y tareas asíncronas ejecutar `php artisan queue:work --tries=5`.

El sitio y el portal usan assets estáticos versionados en `public/assets`. El pipeline Vite del esqueleto sigue disponible con `npm install` y `npm run build`.

## Acceso y seguridad

`/login`, `/register`, recuperación de contraseña, verificación de correo y 2FA TOTP con códigos de recuperación. Roles: administrador general, colaborador y cliente. El colaborador trabaja sobre órdenes asignadas; el cliente opera dentro de su cuenta.

Configurar `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI` con el callback `/auth/google/callback`. Para vincular Google a un correo existente, iniciar sesión primero y usar Seguridad. Google debe devolver un correo verificado; no se vinculan cuentas por simple coincidencia de correo. Una cuenta Google puede establecer contraseña mediante recuperación.

En producción: HTTPS, `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, SMTP real y `PORTAL_REQUIRE_STAFF_2FA=true`. `MAIL_MAILER=log` solo es apropiado para desarrollo: no envía correos y registra enlaces de autenticación en logs privados. Nunca publicar `.env`, ZIP de producción, base de datos, logs o archivos privados. Conservar `APP_KEY` para poder descifrar expedientes existentes.

No se han ejecutado las migraciones nuevas sobre la base de datos existente ni desplegado cambios en producción. El administrador del panel anterior debe prepararse con el comando seguro anterior; no se promueven usuarios automáticamente.

## Validación

`php vendor/phpunit/phpunit/phpunit`, `php vendor/bin/pint --test`, `composer audit`. Las pruebas usan SQLite en memoria y no modifican la base configurada en `.env`.

## Solicitudes y cobros

Las órdenes aceptan varios servicios y cantidades. Las facturas comerciales conservan los importes emitidos, el emisor y el destinatario. Los importes se expresan en centavos enteros y los impuestos en puntos básicos (1800 = 18 %); la moneda predeterminada es DOP. El administrador general registra pagos parciales y sus referencias; un pago repetido con la misma referencia no duplica el cobro.

Preparar resultados, completar trabajo, confirmar pago y publicar entrega son operaciones distintas. Solo el administrador publica, y únicamente con orden completada y factura totalmente pagada. La descarga vuelve a comprobar estas condiciones. Los mensajes de seguimiento no deben utilizarse para enviar resultados; estos pertenecen al módulo de entregas. Los correos notifican actividad sin incluir mensajes ni datos del expediente.

Ver [arquitectura](docs/architecture.md) y [guía de ingeniería](AGENTS.md).

## Portal web

Entrada en `/portal`. Clientes: resumen, solicitudes con varios servicios, documentos privados, facturas, conversaciones, resultados publicados y credenciales de API. Administradores: directorio e invitaciones de usuarios, asignación de responsables, estados operativos, emisión de facturas, confirmación de pagos, publicación, identidad del negocio y auditoría. Colaboradores: órdenes asignadas, documentos, seguimiento y preparación de entregas. `/admin` redirige a configuración solo para administradores.

El sitio público comparte el catálogo, incorpora Validación de Título Universitario y Validación de Exequatur y dispone de formulario de consulta. Las consultas públicas se asocian al cliente únicamente después de demostrar acceso al mismo correo. Los avisos de mensajes llegan por email; las respuestas se escriben en el portal. No se importa automáticamente el correo entrante.

Los clientes generan y revocan tokens y webhooks desde `/portal/security`; la página exige autenticación reciente. También hay una guía de API y descarga del contrato. El staff mantiene 2FA obligatoria. Para cuentas Google sin contraseña, se permite confirmar la identidad volviendo a autenticar la misma cuenta Google.

Facturas comerciales imprimibles desde el navegador; no hay numeración fiscal/NCF, pasarela de pago automática, reembolsos ni conciliación bancaria. Adjuntos permitidos: PDF/JPG/PNG/WebP, hasta 10 MB y 30 documentos de entrada por orden. Logos raster hasta 2 MB y favicon PNG hasta 512 KB. SVG de usuarios no se acepta.

## API y eventos

Endpoints `/api/v1`, tokens por permisos, expiración, revocación, aislamiento por cuenta, idempotencia de creación y errores JSON con request ID. Ver [integración API](docs/api.md) y [OpenAPI](packages/contracts/openapi.json).

Webhooks con HMAC-SHA256 y bandeja transaccional. Configurar `WEBHOOK_ALLOWED_HOSTS`, worker y cron de `php artisan schedule:run` cada minuto. Los intentos fallidos se reintentan con espera creciente y dejan registro operativo. No hay entrega automática de documentos por correo ni archivos públicos.
