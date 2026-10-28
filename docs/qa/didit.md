# QA de la integración Didit

Revisión: 28 de octubre de 2026, America/Santo_Domingo.

## Comprobaciones

- Sesiones privadas cifradas, referencia única por persona, vinculación orden/cuenta y esquema aditivo.
- Creación reclamada una vez; timeout incierto sin segunda llamada facturable; recuperación mediante callback firmado.
- Firma V2 con Unicode, barras, objetos vacíos y claves numéricas; rechazo de firma falsa y replay con header refrescado.
- Rechazo de aplicación, entorno, workflow, persona o sesión ajenos. El cuerpo del callback no aprueba un caso.
- Lectura por API confirmando referencias; comprobaciones ausentes o cédula distinta no producen verified.
- Endpoint de sesión autorizado con orders:write, consentimiento y versión; idempotencia por persona y aislamiento entre clientes.
- Cuotas diarias global/cuenta sin bloquear repetición de la misma sesión; live exige pago total, no abono parcial.
- Entrega automática preparada privada; pago, finalización y publicación antes de resultados API. Una corrección posterior invalida publicación y producción rechaza simulaciones sandbox.
- Switch solo para administración reforzada, indisponible sin configuración; pitch y formulario según modo. Casos antiguos conservan su modo.
- Aviso autorizado y consentimiento explícito antes de iniciar. Redirect con parámetros falsos no modifica el expediente.

## QA visual

`tests/Support/preview-didit.php` creó una SQLite temporal independiente con usuarios/persona ficticios, correo array y sin llamadas Didit reales. Servidor temporal con DB explícita, cookie exclusiva, secretos sintéticos y `--no-reload`; sin worker que pudiera procesar sesiones reales. Se probó login, reconfirmación de contraseña, guardar Manual y Didit, y acceso del cliente al enlace de su persona. No se abrió el enlace externo sintético ni se concedió cámara.

Escritorio 1440 × 1000 y móvil 390 × 844; sin desbordamiento horizontal (scrollWidth 375 para viewport 390). El switch mantiene etiqueta accesible y foco nativo. Es emulación, no prueba física ni certificación de accesibilidad.

Evidencias: [administración escritorio](didit-settings-desktop.png), [administración móvil](didit-settings-mobile.png), [captura cliente móvil](didit-client-mobile.png). La pestaña se cerró y el viewport se restauró. Se verificó que el puerto QA ya no escuchaba y se eliminó únicamente su SQLite sintética, recreable con el helper.

## Actualización local y límites

Se aplicaron exclusivamente las dos migraciones Didit a la SQLite local habitual después de comprobar APP_ENV=local, ruta exacta y lista de pendientes. Antes se creó copia consistente SQLite VACUUM INTO, se verificó integrity_check y conteos. Usuarios, cuentas, órdenes, personas, adjuntos, resultados, facturas y pagos conservaron sus cantidades. Copia privada retenida: `storage/app/private/operational-backups/before-didit-20261028-181821-981dbe16.sqlite` (ignorada por Git).

La instalación local quedó en modo manual, sin configuración Didit lista y sin sesiones. No se alteraron cuentas, claves ni destinos en Didit ni se desplegó producción. El conector se usó solo para inspeccionar aplicaciones/workflows. Falta cargar las credenciales runtime y completar QA real de sandbox/proveedor antes de activar. No se probaron cámaras, biometría real, retorno/webhook externos, contrato comercial o supresión remota.

Validación final: 126 tests y 1.677 aserciones correctas; Pint, composer validate --strict y composer audit correctos, sin avisos de vulnerabilidad. El build pasó; permanece el aviso previo de fontaine opcional. El contrato 1.4.0 cubre los endpoints nuevos y esquemas de modos/conclusiones. Los tests de ejemplos API siguen ejecutando las solicitudes documentadas.
