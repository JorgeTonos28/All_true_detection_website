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

Para cerrar: confirmar una sesión inicial Live con cédula coincidente, obtener `verified`, registrar referencia consentida y completar otro enlace con ACTIVE_3D + Face Match sin nueva cédula. La segunda conclusión debe indicar `verified`, `verification_kind=recurring` y `document_reverified=false`. Ante rechazo, revisión o fallo se analiza ese resultado; no se lo sobreescribe para aprobar la prueba. [Procedimiento y límites](../api-testing.md#piloto-live-controlado).

Los precios dependen de la cuenta, comprobaciones y reintentos aplicables. La página pública consultada anuncia una tabla efectiva el 1 de noviembre de 2026; no se presenta esa tabla futura como precio vigente el 29 de octubre. El piloto no compra saldo ni configura recarga automática. [Precios Didit](https://docs.didit.me/getting-started/pricing).

No se descargaron documentos, retratos o vídeos del proveedor para esta QA. Los diagnósticos imprimen solo estados y booleanos. No se expuso Laravel a Internet ni se desplegó producción. El negocio habitual continúa manual y su `.env` no se modificó. El `package-lock.json` ajeno a este trabajo se conserva sin editar ni incluir en commits.
