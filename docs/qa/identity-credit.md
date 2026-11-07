# QA: vida activa y partners a crédito

28 de octubre de 2026, America/Santo_Domingo. Contrato API 1.5.0.

## Alcance comprobado

- Inspección de solo lectura del workflow publicado mediante el conector: OCR → LIVENESS ACTIVE_3D → FACE_MATCH. No se modificó Didit ni se crearon sesiones reales.
- Preflight obligatorio antes del POST; vida pasiva/configuración no confirmada no crea una sesión. La conclusión exige también ACTIVE_3D en el informe, documento DOM y número coincidente.
- Web sandbox/live: pago total antes de preparar la captura; abono parcial insuficiente. Informe normal sujeto a finalización/publicación. Pruebas registran pagos ficticios, sin dinero real.
- API crédito: cuenta aprobada explícitamente, scopes separados, consentimiento versionado, orden solo de identidad y sin factura individual. Sin conversión web/crédito ni absorción de otros servicios.
- Resultado técnico automático sin prepago/publicación únicamente en endpoint dedicado y con aprobación vigente. No libera documentos ni resultados de otros servicios. Aislamiento entre dos partners aprobados.
- Revocación bloquea tokens existentes y la creación pendiente; se vuelve a comprobar tras el preflight. No cancela llamadas ya en curso ni enlaces ya entregados. Sesiones aceptadas siguen conciliándose sin nuevas notificaciones de conclusión al suspendido.
- El scheduler excluye reservas pendientes suspendidas/no pagadas de los lotes, sin dejar de conciliar sesiones remotas aceptadas. Reautorizar vuelve a admitir las pendientes; no se acapara el lote con trabajo bloqueado.
- Una aceptación por sesión y revisión estable ante polls repetidos. Corrección/retirada incrementa revisión; evento sin PII/conclusión; GET puede pasar de resultado a 409. Sandbox bloqueado en producción.
- Lotes de varias personas no se completan hasta tener sus conclusiones. Bloqueo de orden antes de sesión para serializar conciliación y evitar snapshots cruzados bajo MySQL REPEATABLE READ. Las pruebas locales fueron SQLite; falta ejecutar concurrencia real MySQL en staging/CI.
- Autorización administrativa con 2FA, contraseña reciente y auditoría; cliente no puede autoaprobarse ni emitir scopes de identidad sin aprobación. Panel paginado, aislamiento y límites de período.

## Evidencia visual aislada

`tests/Support/preview-didit.php --credit` preparó SQLite temporal, usuarios/persona ficticios y sesión simulada. Login administrativo y cliente; revisión de gestión sin guardar autorizaciones desde el navegador. Sin worker ni llamadas externas/cámara. Escritorio 1440 × 1000 y móvil 390 × 844; ancho de documento 375 px, sin desbordamiento de página. Navegación de pestañas/tablas con desplazamiento local.

- [Administración, cabecera](identity-credit-admin-desktop.png)
- [Administración, gestión de acceso](identity-credit-admin-metrics.png)
- [Cliente móvil](identity-credit-client-mobile.png)

Se cerró la pestaña, restauró viewport y verificó el puerto 8078 sin listener. Solo se eliminó la SQLite sintética recreable. No sustituye prueba física de cámara, accesibilidad formal o QA remoto real.

## Actualización local

Helper `tests/Support/apply-identity-credit-local.php`: verificó APP_ENV local, SQLite habitual exacta y solo migración aditiva `2026_10_28_000003` pendiente. Copia VACUUM INTO con integrity_check correcto antes de aplicar. Conservó conteos: usuarios 3, cuentas 1, órdenes 2, personas 1, adjuntos 2, resultados 0, facturas 1, pagos 0, sesiones 0. Cero partners autorizados. Segunda ejecución no hizo cambios.

Copia privada retenida e ignorada por Git: `storage/app/private/operational-backups/before-identity-credit-20261028-194632-NjJy6wYj.sqlite`. No se alteraron credenciales ni se activó Didit, ni se migró/desplegó producción.

## Validación final y pendientes

141 tests / 1.831 aserciones correctas. Pint completo y composer validate --strict correctos. Build correcto; aviso previo de fontaine opcional. Auditoría en línea detectó CVE-2026-45070 en MIME según el reporte actual: parche acotado symfony/mime 7.4.19 → 7.4.22 y regresión que rechaza nombres de parámetros con CR/LF, incluido LF final. Auditoría posterior sin avisos; la reconsulta final también terminó sin avisos, pero advirtió uso de metadatos de paquetes en caché por timeout de Packagist. [Aviso upstream](https://github.com/symfony/symfony/security/advisories/GHSA-vqc8-7275-q272).

Pendientes antes de producción: credenciales/runtime y permisos de workflow, consentimiento/condiciones del proveedor, QA real sandbox y live autorizado, cámara/callback HTTPS, prueba MySQL, monitoreo externo. Tarifas, corte e impuestos deben acordarse antes de implementar facturación consolidada. Biometric Authentication recurrente es recomendación documentada: no se activó reutilización de retratos ni se añadió almacenamiento biométrico en ATD.
