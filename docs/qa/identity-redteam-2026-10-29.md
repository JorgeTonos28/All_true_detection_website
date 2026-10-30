# Red teaming de identidad — API ATD → Didit

29 de octubre de 2026 · America/Santo_Domingo · contrato 1.6.2.

## Evidencia reproducible

**46 llamadas HTTP adversas, 145 comprobaciones correctas, cero fallos inesperados.** Runner: `python tests/Support/api-lab/redteam.py`. Reporte privado local: `storage/app/private/api-lab/http-16f9b0218efe/report.json`, SHA-256 `55c92c5a3eec84aa4ed8433a1a5592ece379abffba391111f8964f7dacb87e3b`. Una ejecución anterior tras corregir concurrencia también pasó 46/145.

La suite completa pasó **164 pruebas y 2.025 aserciones**. Son conteos diferentes: no sumarlos como llamadas HTTP. El laboratorio hace peticiones a rutas reales de ATD sobre dos procesos PHP y SQLite WAL aislados, con claves/tokens/archivos propios. Didit se simula con fallos controlados; no se usa la DB habitual ni se consume saldo externo. Ver [alcance general de los doce servicios](api-http-2026-10-29.md).

## Hallazgos reproducidos y corregidos

| Hallazgo | Reproducción y corrección |
| --- | --- |
| Scope de lectura evadido por POST | Un token `identity:verify` no podía leer GET, pero al repetir POST obtenía el enlace privado existente. Ahora POST también exige `identity:read` para devolver ese campo. GET y resultado siguen protegidos. |
| Normalización documental excesiva | Eliminar cualquier carácter no numérico hacía coincidir letras prefijadas/sufijadas y signos con la cédula esperada. Ahora se valida el formato completo de once dígitos, admitiendo solo guiones opcionales en sus posiciones. Los casos malformados quedan no concluyentes. |
| Aprobación anterior durante actualización pendiente | Un evento válido seguido de caída de consulta dejaba accesible la conclusión anterior. Ahora `sync_requested` retiene resultado y dependientes con 409 hasta confirmar. El informe web también queda retenido; una caída no reescribe la conclusión histórica como rechazo. |
| Contención al iniciar reverificaciones | Seis reintentos paralelos de la misma clave y dos claves rivales producían un 500 de SQLite al conciliar. La transacción de lectura/estado admite tres intentos. La creación POST al proveedor queda fuera del reintento. Se mantiene una sola captura y rollback de la orden perdedora. |
| Replay multiplicaba trabajos | Tres copias del mismo evento firmado encolaban tres jobs aunque ya existía recibo. Ahora se confirma el duplicado sin despacho nuevo. Si falló el despacho inicial, el scheduler recupera `sync_requested`. Regresión comprobada con cola interceptada. |
| Enlace sin confirmación de entorno | La URL de creación podía entregarse antes de que una lectura vinculada confirmara el entorno. Ahora permanece nula hasta confirmar sesión/workflow/referencia/entorno y mientras exista actualización pendiente. |

No se cambiaron endpoints ni se relajaron permisos, límites o comprobaciones para hacer pasar la QA. Las correcciones se guardaron en commits independientes de seguridad, concurrencia y laboratorio.

## Intentos adicionales comprobados

- Cuenta ajena, no aprobada, inactiva, no verificada o scope insuficiente no crea sesiones remotas.
- Consentimiento falso/obsoleto y campos para alterar workflow, veredicto o imagen de referencia se rechazan.
- Reverificación no envía retratos ni `expected_details` documentales. Conserva referencia de persona fijada por servidor y metadatos únicos de sesión.
- Seis reintentos sobre dos procesos devuelven la misma orden y un solo POST remoto. Dos claves distintas compiten: una aceptada y otra 409, sin orden residual.
- Suspender partner bloquea lectura, resultado y listado de referencias. Corrección de fuente retira la aprobación recurrente anterior.
- Se comprueban integridad referencial, archivos privados sin huérfanos y errores sin secretos/trazas. El laboratorio no prueba capacidad sostenida ni sustituye MySQL/PHP-FPM productivos.

## Sandbox y captura externa: qué sigue abierto

Ambos enlaces iniciales anteriores ya fueron usados. El backend fijaba `sandbox_scenario=approve`; no se verificó un selector móvil y la afirmación anterior se corrigió. Los presets ahora son una opción de operaciones, nunca del cliente API. [Documentación oficial de Sandbox](https://docs.didit.me/integration/sandbox-testing).

El catálogo público de muestras, consultado con el token documentado de una sesión autorizada, ofrece Brasil, España, Argentina y Polonia. No ofrece una cédula DOM de once dígitos compatible con la solicitud. Ambas sesiones devolvieron Approved para documento DOM, ACTIVE_3D y Face Match, pero su número ficticio no coincide ni cumple formato DOM. ATD conserva `inconclusive`; no permite registrar esa fuente ni crear su captura recurrente. No se ajusta el perfil después de capturar ni se fuerza aprobación.

Por tanto, **la reverificación externa aún no se ha completado**. Los recorridos recurrentes aprobados descritos arriba usan proveedor sintético. Tampoco acreditan precisión biométrica, resistencia ante fotos/vídeo u otros ataques físicos: eso requiere una campaña específica con consentimiento y condiciones del proveedor.

## Piloto Live autorizado

El titular autorizó usar la misma aplicación fuera de modo prueba y asumir el coste de una inicial y una recurrente. El laboratorio incorpora opt-in Live local, entrada personal oculta, consentimiento explícito, preflight de ambos workflows, bloqueo de comprobaciones adicionales y máximo de dos reservas diarias. Producción sigue exigiendo firma webhook y aprobación; la excepción no funciona fuera de APP_ENV local.

El preflight de los dos workflows pasó con lecturas de servidor. **Al cierre de esta ronda no hay solicitudes ni sesiones Live creadas en la campaña.** No se cambió el modo de la aplicación por el agente, no se compraron créditos ni se modificaron workflows/destinos/retención. El usuario debe confirmar el modo Live en consola y ejecutar `python tests/Support/api-lab/pilot.py prepare --live` en su terminal privada. No enviar cédula, dirección, fotos o claves por chat.

### Seguimiento: intento del titular y separación de aplicaciones

Tras ese cierre, el titular ejecutó `prepare --live`. Didit aceptó una sesión `Not Started`, pero la clasificó como `sandbox`; ATD esperaba `live` y retuvo su enlace. El conector confirmó la misma clasificación. No se alteró ese entorno ni se forzó una aprobación. Las sesiones Sandbox no facturan créditos según la documentación del proveedor; no se afirma que una marca local de aceptación equivalga a un cargo Live.

Se identificaron dos aplicaciones distintas: «All true detection (Sandbox)», usada por la clave anterior, y «My Application», donde otra sesión existente confirma `environment=live`. Cambiar la vista de la consola no cambia la clave runtime. La recomendación anterior de quitar el modo de prueba de la misma aplicación fue incompleta y se corrigió con [el modelo oficial](https://help.didit.me/account-organization/organizations-and-applications).

Se crearon y releyeron dos workflows nuevos publicados, no predeterminados, en «My Application»: `aa2b516d-9563-46f9-adb9-0d484ca6cfaf` inicial DOM → ACTIVE_3D → Face Match y `02d83807-8d18-4e64-af3d-e28e47581713` recurrente ACTIVE_3D → Face Match. Sin búsqueda 1:N, audio ni otros módulos. No se modificaron workflows anteriores, destinos o retención; no se creó ninguna captura al configurarlos.

El titular aportó la clave Live directamente en `.env` como `DIDIT_LIVE_API_KEY`; se añadieron los tres IDs no secretos. El preflight aislado de ambos workflows pasó con esa clave y comprobó **cero órdenes/cero sesiones**. Las credenciales normales permanecen separadas. El marcador conserva el perfil para consultar el intento anterior con su clave original.

El diagnóstico ahora distingue entorno esperado/observado sin exportar el cuerpo de decisión, URL o evidencia. La recuperación `new --live` exige que el intento anterior esté vinculado, confirmado Sandbox y `Not Started`, sin captura recurrente; el perfil destino debe estar completo y pertenecer a otra aplicación. Las regresiones rechazan estado iniciado, resultado final, entorno Live, vinculación incierta, perfil incompleto y la misma aplicación. No se admite repetir a ciegas un posible cargo Live.

Las dos capturas Live y la reverificación externa continúan pendientes de que el titular ejecute el comando y complete los enlaces en su celular. No se ejecutó la cámara ni se descargaron medios del proveedor.

La validación posterior pasó **165 pruebas Laravel y 2.032 aserciones**, más las dos regresiones Python de recuperación (`python -m unittest discover -s tests/Support/api-lab -p test_pilot.py`). Sintaxis Python, Pint y diff correctos. El diagnóstico real del intento devuelve vinculación confirmada, esperado Live, observado Sandbox y `Not Started`, sin revelar enlace ni perfil personal.

### Seguimiento: creación Live sin confirmar y crédito pendiente

El titular ejecutó después `new --live` con su clave Live. ATD archivó el intento Sandbox no iniciado y creó una nueva reserva local, pero esta terminó `create_uncertain`, sin ID remoto ni aceptación confirmada. La búsqueda exacta por referencia en «My Application» no encontró sesión. El conector mostró saldo US$0.00, sin recarga automática; el workflow publicado confirmado usa ACTIVE_3D y no white label. El módulo activo es de pago y no pertenece al cupo gratuito de vida pasiva. [Explicación oficial de crédito](https://help.didit.me/billing-credits/not-enough-credits).

El error HTTP original no se había conservado: no se afirma retrospectivamente que fuese un 402 ni se transforma esta reserva en un rechazo confirmado. Sí se corrigió el diagnóstico que mostraba un supuesto problema de entorno aunque no existía ID remoto. No se repitió el POST de creación. El titular indicó que agregaría saldo después; las capturas Live inicial y recurrente siguen pendientes. Una recarga no resuelve automáticamente una creación incierta.

Las nuevas respuestas fallidas guardan solo status HTTP, código técnico permitido y certeza del rechazo en auditoría privada. Los 4xx explícitos admitidos quedan `provider_rejected`; transportes, 429, 5xx y respuestas inválidas permanecen inciertos. Un 502 que menciona crédito sigue sin autorizar reintento. Ninguno significa un rechazo biométrico de la persona; ambos se exponen como `attention_required` y cuentan en atención operativa.

La conciliación de creación solo hace GET: filtra referencia/workflow, exige candidato único y confirma entorno, referencia y metadata ATD mediante la lectura de decisión. Recupera el recibo mínimo de la sesión existente y encola la conciliación normal; no repite POST ni exporta evidencia. Cero candidatos, varios, metadata distinta o error de lectura conservan el bloqueo. El comando operativo y el helper del piloto usan esa misma lógica, cada uno en su DB/entorno correcto.

**Validación final de esta corrección: 174 pruebas Laravel, 2.080 aserciones y tres pruebas Python correctas.** Incluye rechazo explícito, 502 ambiguo, 429, recibo recuperado solo con lecturas, búsqueda vacía sin recreación, varios candidatos, metadata ajena y comando operativo sin POST. El estado real del piloto permanece incierto; `status` lo explica sin solicitar nuevos datos. No se compró saldo, no se cambió el flujo a vida pasiva ni se alteró la configuración del negocio habitual.

Para cerrar: confirmar una sesión inicial Live con cédula coincidente, obtener `verified`, registrar referencia consentida y completar otro enlace con ACTIVE_3D + Face Match sin nueva cédula. La segunda conclusión debe indicar `verified`, `verification_kind=recurring` y `document_reverified=false`. Ante rechazo, revisión o fallo se analiza ese resultado; no se lo sobreescribe para aprobar la prueba. [Procedimiento y límites](../api-testing.md#piloto-live-controlado).

Los precios dependen de la cuenta, comprobaciones y reintentos aplicables. La página pública consultada anuncia una tabla efectiva el 1 de noviembre de 2026; no se presenta esa tabla futura como precio vigente el 29 de octubre. El piloto no compra saldo ni configura recarga automática. [Precios Didit](https://docs.didit.me/getting-started/pricing).

No se descargaron documentos, retratos o vídeos del proveedor para esta QA. Los diagnósticos imprimen solo estados, entorno y booleanos. No se expuso Laravel a Internet ni se desplegó producción. El negocio habitual continúa manual; su `.env` recibió únicamente el grupo separado del piloto Live, conservando sus credenciales originales y sin aprobación productiva. El `package-lock.json` ajeno a este trabajo se conserva sin editar ni incluir en commits.
