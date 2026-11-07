# QA de la integración Didit

Revisión: 28 de octubre de 2026, America/Santo_Domingo.

## Comprobaciones

- Sesiones privadas cifradas, referencia única por persona, vinculación orden/cuenta y esquema aditivo.
- Creación reclamada una vez; timeout incierto sin segunda llamada facturable; recuperación mediante callback firmado.
- Firma V2 con Unicode, barras, objetos vacíos y claves numéricas; rechazo de firma falsa y replay con header refrescado.
- Rechazo de aplicación, entorno, workflow, persona o sesión ajenos. El cuerpo del callback no aprueba un caso.
- Lectura por API confirmando referencias; comprobaciones ausentes o cédula distinta no producen verified.
- Endpoint API de sesión autorizado con identity:verify y aprobación vigente del partner; consulta con identity:read. Consentimiento y versión; idempotencia por persona y aislamiento entre clientes. Web conserva acción con política de prepago.
- Cuotas diarias global/cuenta/canal sin bloquear repetición de la misma sesión; web sandbox/live exige pago total, no abono parcial. API crédito requiere aprobación explícita y cupo, no prepago.
- Informe web preparado privado; pago/finalización/publicación antes de su entrega. API crédito devuelve únicamente conclusión técnica automáticamente, no documentos. Corrección invalida publicación web o incrementa revisión/aviso API; producción rechaza simulaciones sandbox.
- Switch solo para administración reforzada, indisponible sin configuración; pitch y formulario según modo. Casos antiguos conservan su modo.
- Aviso autorizado y consentimiento explícito antes de iniciar. Redirect con parámetros falsos no modifica el expediente.

## QA visual

`tests/Support/preview-didit.php` creó una SQLite temporal independiente con usuarios/persona ficticios, correo array y sin llamadas Didit reales. Servidor temporal con DB explícita, cookie exclusiva, secretos sintéticos y `--no-reload`; sin worker que pudiera procesar sesiones reales. Se probó login, reconfirmación de contraseña, guardar Manual y Didit, y acceso del cliente al enlace de su persona. No se abrió el enlace externo sintético ni se concedió cámara.

Escritorio 1440 × 1000 y móvil 390 × 844; sin desbordamiento horizontal (scrollWidth 375 para viewport 390). El switch mantiene etiqueta accesible y foco nativo. Es emulación, no prueba física ni certificación de accesibilidad.

Evidencias: [administración escritorio](didit-settings-desktop.png), [administración móvil](didit-settings-mobile.png), [captura cliente móvil](didit-client-mobile.png). La pestaña se cerró y el viewport se restauró. Se verificó que el puerto QA ya no escuchaba y se eliminó únicamente su SQLite sintética, recreable con el helper.

## Actualización local y límites

Se aplicaron exclusivamente las dos migraciones Didit a la SQLite local habitual después de comprobar APP_ENV=local, ruta exacta y lista de pendientes. Antes se creó copia consistente SQLite VACUUM INTO, se verificó integrity_check y conteos. Usuarios, cuentas, órdenes, personas, adjuntos, resultados, facturas y pagos conservaron sus cantidades. Copia privada retenida: `storage/app/private/operational-backups/before-didit-20261028-181821-981dbe16.sqlite` (ignorada por Git).

La instalación local quedó en modo manual, sin configuración Didit lista y sin sesiones. No se alteraron cuentas, claves ni destinos en Didit ni se desplegó producción. El conector se usó solo para inspeccionar aplicaciones/workflows. Falta cargar las credenciales runtime y completar QA real de sandbox/proveedor antes de activar. No se probaron cámaras, biometría real, retorno/webhook externos, contrato comercial o supresión remota.

Validación inicial de la integración: 126 tests y 1.677 aserciones correctas; Pint, composer validate --strict y composer audit correctos en ese momento. El build pasó; permanece el aviso previo de fontaine opcional. El contrato inicial era 1.4.0. Para el contrato 1.5.0, prepago web también en sandbox y excepción API crédito, ver [validación actualizada](identity-credit.md).
