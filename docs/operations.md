# Operación y despliegue

## Preparar entorno

PHP 8.3+ con PDO MySQL/SQLite, mbstring, OpenSSL, cURL, fileinfo y XML; GD para validación de imágenes. El lock de Composer se resuelve contra PHP 8.3 para evitar dependencias que solo funcionen en el PHP de desarrollo. MySQL 8+ o SQLite; la suite de CI cubre ambos motores. Los assets del portal son estáticos y están en Git.

Antes de actualizar, crear y verificar un respaldo de base de datos, `storage/app/private`, logos, configuración privada y APP_KEY. Conservar APP_KEY: cifra mensajes, descripción de expedientes, resultados, secretos de webhooks y trabajos de autenticación en cola. Una clave distinta impide recuperar los datos. El almacenamiento privado y sus respaldos deben tener acceso restringido y cifrado de disco/servicio de almacenamiento; no colocarlos bajo el document root.

### Acceso administrativo local sin correo

Cuando el SMTP no esté disponible en desarrollo, `php artisan portal:create-local-admin correo@example.com` permite crear o preparar un administrador local solicitando la contraseña de forma interactiva. El comando marca el correo como verificado, revoca sesiones/tokens anteriores y no envía nada por email. Solo funciona si `APP_ENV=local` y `APP_DEBUG=true`; producción debe usar `portal:create-admin` y su flujo normal de recuperación.

### Enlaces de invitación y verificación

En local, usar `APP_URL=http://127.0.0.1:8000`, incluyendo el puerto. En producción usar el dominio HTTPS canónico. Las notificaciones de autenticación conservan esa dirección al encolarse y generan sus rutas sin depender del host de la petición o del worker. Tras cambiar `.env`, ejecutar `php artisan config:clear` y reiniciar `composer run dev` completo; en producción volver a generar la caché y reiniciar los workers. Los procesos abiertos pueden conservar variables anteriores. Los correos ya enviados no cambian: solicitar otro enlace una vez reiniciados los procesos.

Probar la invitación en una ventana privada, sin la sesión del administrador que la envió. El usuario establece su contraseña, recibe la verificación de correo, inicia sesión y confirma ese enlace. El personal debe además configurar 2FA. Un reenvío dentro del minuto de espera muestra un aviso; no asegura un envío inexistente. Usar el último enlace recibido, pues una nueva solicitud invalida el token anterior.

### Directorio de usuarios

Solo administradores con 2FA y confirmación reciente de identidad acceden al directorio, sus fichas y la búsqueda de cuentas. La tabla pagina en servidor (25 usuarios), aplica filtros por rol/acceso y busca tras 300 ms de pausa. Coincide con cualquiera de hasta 12 palabras (200 caracteres en total), sin distinguir mayúsculas, en identificadores, nombre, email, rol, fechas administrativas y datos de cuenta (empresa, correo de facturación, identificador fiscal y dirección). También reconoce etiquetas de rol, acceso y verificación. No consulta hashes, tokens, Google ID ni secretos/códigos 2FA. Los comodines `%`, `_` y `!` se buscan literalmente. La búsqueda de cuentas devuelve como máximo 20 resultados. Para volúmenes que requieran búsqueda indexada de subcadenas, evaluar un índice específico manteniendo esta semántica y autorización; `LIKE %texto%` recorre campos del directorio.

Al pulsar una fila o Gestionar se abre el modal; Escape o Cerrar lo cierran. Rol, desactivación y reenvío conservan las mismas autorizaciones, revocación de sesiones/tokens y auditoría del servidor. Sin JavaScript, los enlaces de ficha abren una página equivalente y la búsqueda funciona al enviar el formulario.

Configurar el document root en `public/`, un usuario de BD exclusivo y permisos mínimos para aplicación y migraciones. Instalar dependencias con `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`. Nunca subir `.env`, SQLite, logs o ZIP antiguos al repositorio ni publicar el proyecto completo como carpeta servida por HTTP. El ZIP anterior de producción queda como archivo local heredado y no debe reutilizarse para desplegar el portal.

## Configuración necesaria

- URL HTTPS canónica en APP_URL, APP_ENV=production, APP_DEBUG=false, SESSION_ENCRYPT=true y SESSION_SECURE_COOKIE=true. No activar debug en un sitio accesible por clientes. Si hay un proxy inverso, configurar explícitamente los proxies de confianza en Laravel según el proveedor antes de validar IP, HTTPS y límites; no confiar indiscriminadamente en encabezados reenviados.
- Base de datos y APP_KEY propios de la instalación, sin credenciales por defecto. La migración de seguridad desactiva cuentas que conserven la antigua contraseña fija; preparar el propietario con `portal:create-admin correo@empresa.com`. El comando rota su contraseña y envía recuperación. Confirmar que ese correo pertenece al responsable autorizado.
- SMTP real y remitente autorizado/DKIM/SPF. Para la cuenta de ATD, el transporte SMTP seguro usa `mail.alltruedetection.com` en el puerto `465`, con `MAIL_SCHEME=smtps` y usuario `info@alltruedetection.com`. `MAIL_PASSWORD` es un secreto: se configura en el gestor de secretos o `.env` del servidor y nunca se copia a Git ni al chat. `MAIL_MAILER=log` no entrega mensajes y deja enlaces de autenticación en logs: solo usar en desarrollo. Mantener los logs privados. Verificar que el worker envía recuperación, invitación y verificación de correo en español.
- `MAIL_AUDIT_BCC=info@alltruedetection.com` añade una copia oculta de cada correo que sale de la aplicación, incluidos correos de autenticación y notificaciones encoladas. La cuenta receptora debe tener acceso restringido y una política de retención adecuada porque puede recibir enlaces sensibles. La copia se incorpora justo antes de la entrega SMTP, por lo que también cubre trabajos que se procesan después del commit.
- El servidor IMAP/POP3 de la cuenta no se configura en esta versión: el portal no importa respuestas entrantes. Los clientes y el personal responden las conversaciones desde la UI.
- GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET y GOOGLE_REDIRECT_URI en la consola OAuth correspondiente. Registrar exactamente `/auth/google/callback` en el dominio HTTPS de ATD. Probar una cuenta nueva, vinculación autenticada y desafío 2FA en staging. Las pruebas automatizadas usan respuestas Google simuladas y no sustituyen esta comprobación real.
- PORTAL_REQUIRE_STAFF_2FA=true. El personal no puede operar hasta confirmar TOTP. Guardar códigos de recuperación. El reinicio excepcional solo está disponible por consola, con identidad ya verificada y auditoría; no existe un bypass público de 2FA.
- DOCUMENT_SCAN_BINARY: ruta absoluta de clamdscan o clamscan con firmas actualizadas. Permitir lectura de temporales al usuario PHP, sin ejecutar PHP como root. La app verifica extensión, MIME, tamaño y antivirus antes de almacenar. Código de salida 0 permite; 1 rechaza; timeout/error o falta de configuración bloquean cargas en producción. Ajustar `upload_max_filesize=10M`, `post_max_size=24M`, `max_file_uploads=5` y el límite del servidor a 24 MB (o límites menores por ruta). La creación web acepta 5 documentos / 20 MB en conjunto; las cargas individuales web/API, 10 MB. La app rechaza cuerpos de más de 22 MB en creación o 12 MB en carga individual. Aplicar esos límites también al proxy/servidor para cubrir transferencias chunked antes de PHP. El análisis tiene un presupuesto total de 45 s y hasta 15 s por archivo; ajustar el timeout de petición/servidor a 60 s o más. Verificar con un PDF real y un archivo de prueba antivirus controlado en staging.
- Documentos admitidos: PDF, DOCX sin macros/objetos incrustados/cifrado y JPG/PNG/WebP de hasta 40 megapíxeles y 10.000 píxeles por lado. Convertir Word antiguo `.doc` a DOCX o PDF; no se aceptan audio, vídeo, SVG, ZIP sueltos ni Word con macros. PHP necesita `ext-zip` para inspeccionar DOCX. Se guardan tamaño y SHA-256, con cuotas de 100 MB por solicitud, 1 GB por cuenta y 30 documentos de entrada. La carga inicial y la solicitud son atómicas y se retiran archivos del intento fallido. Después de actualizar ejecutar `php artisan portal:document-metadata` para preparar documentos previos; si faltan metadatos, la cuenta no admite más archivos hasta completarlos. El comando no cambia su contenido ni elimina archivos.
- PORTAL_CURRENCY=DOP por defecto; confirmar la moneda de negocio antes de emitir la primera factura. El impuesto se define explícitamente por factura. No se generan NCF ni documentos fiscales homologados.
- WEBHOOK_ALLOWED_HOSTS: hosts exactos, públicos y HTTPS aprobados por operaciones. El escáner de URL rechaza destinos internos y redirecciones; aplicar también restricciones de salida de red. API_ALLOWED_ORIGINS permanece vacío para integraciones servidor a servidor; declarar orígenes exactos para navegadores si se necesitan, sin cookies.

## Activación

1. En ventana controlada, detener tráfico mutante y workers durante la actualización.
2. `php artisan migrate --force` y `php artisan db:seed --force`. Los seeders usan firstOrCreate y no sobrescriben la marca existente.
3. `php artisan storage:link`. Solo logos y favicon usan el disco público; el disco local privado no tiene rutas de descarga genéricas.
4. `php artisan config:cache`, `php artisan route:cache` y `php artisan view:cache` después de configurar variables. Reiniciar workers tras cambios de código/configuración.
5. Mantener `php artisan queue:work --tries=5 --timeout=60` supervisado. `retry_after` debe exceder el timeout. Los emails de autenticación se encolan cifrados y después de commit. En Windows, `composer run dev` inicia servidor, cola, Vite y `schedule:work` en una sola terminal. Las pruebas de webhooks se despachan al siguiente minuto. Pail no se incluye porque requiere `pcntl`, que no forma parte de PHP para Windows. Consultar `storage/logs/laravel.log` para los logs.
6. Cron cada minuto: `php artisan schedule:run`. El scheduler despacha webhooks, elimina tokens expirados y purga métricas API de más de 30 días. Revisar failed_jobs, endpoints desactivados y entregas agotadas. Corregir la causa antes de reintentar: los webhooks agotados admiten replay autorizado conservando evento/payload y su historial; el correo se reintenta desde operaciones de cola. El envío remoto no mantiene transacciones abiertas y usa leases de 60 s. La rotación del secreto firma con ambas claves durante 24 h; no permite una nueva rotación durante esa transición. Los destinos se vuelven a validar en cada intento.
   El worker también procesa el canal de notificaciones de base de datos. Cada usuario ve exclusivamente su bandeja; abrir una notificación la marca atendida y dirige a una ruta que vuelve a autorizar el recurso. Los emails de actividad, acceso y verificación comparten plantilla de marca con alternativa de texto. No se envían contenidos de mensajes ni informes finales en el correo.
   Una entrega de webhook agotada avisa a los clientes activos/verificados de la cuenta y a los administradores con enlace al diagnóstico; no incluye el payload ni el secreto.
7. Verificar flujos reales de registro, verificación, recuperación, Google, 2FA, invitaciones, solicitudes y documentos. Emitir una factura de prueba, registrar abono y saldo, comprobar que no se ve ninguna entrega antes de pago/publicación y completar la descarga con un cliente autorizado. Probar revocación y firma de eventos con una integración de staging.

### Prueba de correo local o de staging

Para una prueba real con el buzón de ATD, configurar el secreto sin pegarlo en el repositorio:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.alltruedetection.com
MAIL_PORT=465
MAIL_USERNAME=info@alltruedetection.com
MAIL_PASSWORD=<secreto-del-buzón>
MAIL_FROM_ADDRESS=info@alltruedetection.com
MAIL_FROM_NAME="All True Detection"
MAIL_EHLO_DOMAIN=alltruedetection.com
MAIL_AUDIT_BCC=info@alltruedetection.com
```

Después de cambiar `.env`, limpiar la configuración y enviar la prueba síncrona:

```powershell
php artisan config:clear
php artisan portal:test-mail destinatario-de-prueba@example.com
```

El comando usa el transporte configurado y la copia BCC se añade automáticamente. Para validar además los avisos encolados, dejar un worker activo y ejecutar una acción de prueba del portal; revisar `failed_jobs` si el correo no sale. Mientras no se configure una contraseña válida, conservar `MAIL_MAILER=log` en local: ese transporte solo registra el mensaje y no contacta al servidor.

## Límites de esta entrega

Una cuenta puede tener varios usuarios cliente con acceso compartido a sus órdenes. Los colaboradores solo ven órdenes asignadas; no existe delegación adicional por departamento dentro de una cuenta. Los documentos de entrada los aporta el cliente, y los resultados se publican desde el módulo de entregas; no deben enviarse resultados por el texto de mensajes.

La factura emitida es comercial e inmutable. Para cambios de alcance posteriores se crea una nueva orden. No hay pasarela automática, reembolsos, conciliación bancaria, comprobantes fiscales ni ingestión de respuestas por email. Las conversaciones se responden desde el portal. Los pagos se registran únicamente por administrador y no se eliminan desde la UI.

No hay eliminación automática de expedientes ni borrado desde el portal. Definir con ATD una política de retención y un procedimiento de supresión autorizado antes de cargar información real; preservar las obligaciones operativas/fiscales que correspondan. La app no toma decisiones automáticas sobre personas ni ejecuta las verificaciones por sí misma: gestiona el trabajo y la entrega profesional de ATD.

Las pruebas locales usan SQLite. El workflow verifica PHP 8.3/SQLite y PHP 8.4/MySQL; revisar sus resultados antes de integrar y desplegar. La revisión visual de esta entrega usó una base temporal con datos ficticios y correo array: escritorio y viewport móvil de 390 px, solicitud con selector/filtro, notificación atendida, consola API e historial, documentación y búsqueda de operaciones. No sustituye QA en dispositivo físico ni las pruebas reales de SMTP/OAuth/antivirus/webhooks en staging antes de abrir producción.
