# QA — identidades reutilizables

Revisión: 28 de octubre de 2026, America/Santo_Domingo. Contrato público 1.6.0.

## Alcance comprobado

- Esquema aditivo con fuente inicial única, FKs, perfil cifrado, identificador opaco, aislamiento de cuenta/aplicación/entorno y campos protegidos contra asignación masiva.
- Alta consentida después de fuente documental verificada y entregada; reconfirmación GET. No se convierte una revisión manual o autenticación recurrente en nuevo ancla documental. Repetir alta no renueva ni reactiva.
- API: scopes, partner vigente, separación de cuentas, rechazo de retrato/cédula arbitrarios, idempotencia y respuesta sin perfil para permiso de escritura. Web: nueva orden/factura, pago previo, entrega posterior solo completada/pagada/publicada.
- Nueva captura con ACTIVE_3D + Face Match sin OCR ni transmisión de cédula/media, vendor estable + metadata única. Callback firmado recupera el intento correcto; payload del evento no aprueba.
- Reserva única hasta cierre remoto; cancelación antes del POST libera, creación incierta no se reintenta. Revocación durante preflight bloquea el POST. Binding alterado impide entregar una conclusión verificada.
- Vigencia de 90 días anclada al flujo inicial, sin ampliación por selfies. Caducidad posterior conserva fecha de comprobaciones históricas válidas. Corrección adversa de la fuente bloquea GET y solicita conciliación de revisiones dependientes.
- Polling sin túnel solo local/sandbox; producción/live no prescinden de firma. Callback sin secreto sigue rechazado. El POST Sandbox incluye guardia sandbox_scenario, ausente en live.
- Diagnóstico lee los dos workflows, no crea sesiones ni imprime credenciales. OpenAPI válido y cobertura de todas las rutas/referencias; documentación pública renderizada.

## Proveedor

Se crearon/publicaron con autorización únicamente dos workflows nuevos en aplicación `145cffa4-94fe-4494-8442-872e7f63c928` «All true detection (Sandbox)». Readback confirmó estado publicado y grafos:

- Inicial `b80f4753-2dbc-46f3-b207-227e19ff735b`: OCR DOM ID → ACTIVE_3D → Face Match.
- Recurrente `ffa07f01-d206-494d-be95-9c5dd8fa86aa`: ACTIVE_3D → Face Match, sin OCR.

Sin búsqueda facial 1:N ni grabación adicional de audio; no se cambiaron workflows anteriores, retención ni destinos webhook. Returned data permanece predeterminado (el proveedor puede devolver todos los datos de esas features); el adaptador ATD solo persiste conclusión mínima, nunca descarga medios. El conector no expuso modo real de aplicación ni clave completa. Debe confirmarse Sandbox en consola y aportar clave runtime privada para preflight/captura externos. No se crearon sesiones, transmitieron documentos/capturas ni cambiaron credenciales de la cuenta.

No se validaron cámara, precisión biométrica, webhooks externos, retención efectiva, contrato, residencia ni supresión. Sandbox procesa la experiencia de captura, pero simula los datos/decisiones y conserva los medios capturados. Su cédula ficticia podría diferir de la requerida DOM; ATD no lo aprobará indebidamente. Esa respuesta aún necesita ensayo con credenciales. Las pruebas de dominio usan HTTP sintético vinculado y no equivalen a una verificación real del proveedor.

## Interfaz y limpieza

`tests/Support/preview-reusable-identity.php` creó una SQLite temporal separada, usuarios/persona ficticios y respuestas HTTP simuladas. El servidor QA usó esa DB explícitamente, cookie exclusiva, correo array y claves falsas; no hubo worker ni captura externa. Login del cliente, registro de referencias, apertura/cierre de modal y retorno de foco con Escape comprobados.

Escritorio 1440 × 1000 y móvil 390 × 844. Ancho del documento 375 para viewport 390; modal 340 px sin desbordamiento horizontal (contenido 323 px). Formulario desplazable verticalmente y controles etiquetados. Emulación, sin certificación de accesibilidad ni prueba física. Evidencias: [escritorio](reusable-identity-desktop.jpg), [móvil](reusable-identity-mobile.jpg). Se restauró viewport, cerró pestaña de QA, detuvo servidor y confirmó puerto 8007 libre; se eliminó únicamente la SQLite sintética validada por ruta.

## Base local y configuración

Aplicada únicamente `2026_10_28_000004_add_reusable_identity_references`, después de confirmar APP_ENV local, SQLite exacta, única migración permitida pendiente y copia VACUUM INTO con integrity_check. Backup privado retenido: `storage/app/private/operational-backups/before-identity-credit-20261029-010639-cCBALU2y.sqlite` (fecha UTC, ignorado por Git).

Conteos conservados: usuarios 3, cuentas 1, órdenes 2, personas 1, adjuntos 2, resultados 0, facturas 1, pagos 0, sesiones 0. No se autorizaron partners ni crearon identidades en la DB habitual. `.env` local preparado con IDs de pruebas, avisos autorizados y polling; clave ausente, producción no aprobada, modo efectivo manual. No se desplegó producción.

## Validación

Suite final: **156 pruebas / 1.976 aserciones correctas**. Después de cerrar la documentación se repitieron sus tres pruebas de contrato/renderizado: 731 aserciones correctas. Pint correcto; JavaScript del modal validado con node --check. Build de producción correcto, con aviso previo de fontaine opcional. composer validate --strict correcto; composer audit --locked consultó Packagist y no encontró avisos de vulnerabilidades. No se modificaron dependencias ni el package-lock.json preexistente del usuario.
