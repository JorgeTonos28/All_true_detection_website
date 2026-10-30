# Laboratorio HTTP de la API

El runner usa Python 3 (solo biblioteca estándar) y el PHP/vendor del proyecto. Arranca dos servidores PHP temporales en **127.0.0.1** con puertos libres y llama por HTTP a las rutas Laravel reales. No registra rutas de depuración en la app ni abre un servidor a la red local/Internet.

```bat
python tests/Support/api-lab/run.py
```

Cada ejecución crea una carpeta nueva bajo `storage/app/private/api-lab/http-*` con SQLite WAL, APP_KEY, caché, almacenamiento, logs y tokens ficticios propios. Valida la ruta y el marcador antes de arrancar. La DB habitual, sus archivos y usuarios no se usan. No requiere cambiar `.env`. El correo se intercepta y el proveedor Didit se simula dentro del laboratorio; las llamadas del consumidor a ATD sí son HTTP real. Las peticiones salientes no previstas están bloqueadas.

La carpeta privada conserva `report.json`, fixtures y logs para reproducir los hallazgos; nunca añadirla a Git o publicar `manifest.json`, claves o enlaces. Los procesos se detienen al finalizar. Los documentos PDF/PNG/DOCX se fabrican en memoria y no contienen información real. No se prueba un antivirus real ni la capacidad de producción de PHP-FPM/MySQL: el laboratorio conserva la validación de contenido, pero omite el escáner local. La suite de regresión cubre además fallo cerrado del escáner de producción.

## Escenarios

- `authentication-contract`: falta de token, token vencido, cuentas inactivas/no verificadas, staff sin API, scopes, JSON inválido/excesivo y parámetros malformados.
- `all-services`: los doce servicios del catálogo; creación/idempotencia, evidencia, mensajes, aislamiento, cotización, factura, abono parcial, entrega privada, pago completo, publicación y descarga con SHA-256. Operaciones administrativas se ejecutan mediante las acciones compartidas en CLI, porque no existe API administrativa para confirmar pagos/publicar; los clientes continúan usando sus endpoints HTTP reales.
- `files-batches`: título + exequátur + identidad por varias personas, categorías obligatorias, referencias cruzadas, duplicados, documentos adulterados, macros, formatos prohibidos, tamaño, dimensiones y previsualización privada.
- `identity`: captura inicial, consentimiento, scopes, eventos firmados/falsos/repetidos, resultado técnico a crédito, referencia reutilizable, reverificación, suspensión, revocación y corrección de fuente. Se fuerzan rechazo, revisión, vida pasiva, comprobaciones ausentes y cédula distinta.
- `identity-resilience`: timeout después de aceptar remotamente la creación, recuperación firmada sin repetir POST, eventos con aplicación/workflow/entorno incorrectos, replay con timestamp antiguo, caída del proveedor al reutilizar, caducidad de referencia, preflight pasivo y cuota diaria.
- `concurrency-limits`: ocho reintentos concurrentes de la misma solicitud sobre dos procesos y la misma DB; una sola orden. Sesenta lecturas por minuto permitidas y bloqueo 429 con Retry-After después del límite. La carga undécima se bloquea, pero deja disponible la cuota independiente de identidad.

Para repetir grupos concretos:

```bat
python tests/Support/api-lab/run.py --only files-batches,identity
```

Los lotes de validación reinician únicamente la caché del laboratorio entre grupos, para que una cuota agotada no oculte la regla que se pretende probar. El grupo de límites mantiene los contadores y comprueba expresamente su bloqueo. El informe diferencia llamadas HTTP y comprobaciones sobre sus respuestas; estos números no son usuarios concurrentes ni un benchmark de producción.

La ejecución completa compara también las operaciones ejercitadas con todas las rutas del OpenAPI actual. El informe contiene nombres, códigos, tiempos e identificadores sintéticos, sin tokens ni enlaces de captura. Los grupos seleccionados no exigen cobertura de las operaciones ajenas a su alcance.

Para el red teaming específico de identidad:

```bat
python tests/Support/api-lab/redteam.py
```

Simula otra app con tokens de permisos distintos, intentos de acceso entre cuentas, manipulación de parámetros del proveedor, documentos malformados, seis reintentos recurrentes paralelos sobre dos servidores y dos claves rivales. Comprueba un solo POST al proveedor, rollback de la solicitud perdedora, ausencia de retratos/documentos en la petición recurrente, suspensión y retirada de resultados durante una actualización no confirmada. El proveedor es sintético; estas pruebas no consumen saldo ni evalúan detección biométrica real.

## Captura externa desde el celular

El piloto externo usa otra base separada y la clave Didit Sandbox de `.env`. No ejecuta los casos sintéticos ni modifica el modo del negocio habitual. A diferencia del runner anterior, **sí crea una sesión real de Sandbox**, mediante la API HTTP de ATD y sus acciones de integración, para que el titular abra el enlace HTTPS privado de Didit. La primera preparación persiste las claves de idempotencia antes de enviar y los reintentos recuperan la misma solicitud/sesión.

```bat
python tests/Support/api-lab/pilot.py prepare
```

La pantalla alojada puede abrirse desde Safari/Chrome móvil sin exponer localhost. Usar los documentos de muestra de Sandbox y seguir la captura ACTIVE_3D. El escenario queda preseleccionado por el backend (`approve` por defecto): no se promete un selector en el celular. El veredicto es simulado, pero Didit conserva los medios capturados realmente. La prueba no acredita precisión biométrica ni verifica un documento real. El usuario realiza la captura y concede cámara desde su dispositivo; el agente no maneja su cámara.

Tras terminar, consultar desde la computadora:

```bat
python tests/Support/api-lab/pilot.py status
```

El comando concilia por la API de servidor y muestra solo estado/conclusión mínima. Para solicitar la captura recurrente, una vez aprobada y vinculada la inicial:

```bat
python tests/Support/api-lab/pilot.py recurrent
```

No se crea la sesión recurrente si la inicial no está `verified`; el mock documental puede devolver una cédula diferente de la DOM ficticia solicitada. Ese caso queda no concluyente sin alterar controles. El enlace y los resultados se guardan únicamente en el laboratorio privado; `active-pilot.json` apunta a ese entorno y permite retomar el ensayo. No borrar ese archivo ni repetir con otra clave ante un POST incierto: conciliar la sesión existente.

`python tests/Support/api-lab/pilot.py diagnose` muestra únicamente estados de comprobaciones, método activo, país del documento e indicadores booleanos de formato/coincidencia; no imprime números de cédula, imágenes, puntuaciones biométricas ni respuesta bruta. Si se necesita **otra** captura inicial independiente de Sandbox, ejecutar explícitamente `python tests/Support/api-lab/pilot.py new`: conserva el estado previo en su carpeta privada. Para preseleccionar rechazo, por ejemplo:

```bat
python tests/Support/api-lab/pilot.py new --scenario decline_document_expired
```

También se admiten `decline_face_match_low_similarity` y `review_face_match_borderline`. Un preset no cambia una sesión ya creada. No usar `new` para resolver una creación incierta o eludir límites.

El catálogo de muestras consultado solo ofrece Brasil, España, Argentina y Polonia, sin cédula DOM compatible. Los dos enlaces iniciales anteriores ya fueron usados; ambos quedaron no concluyentes en ATD por discrepancia documental. No sirven como fuente de una captura recurrente ni se deben anunciar como enlaces nuevos. Esa parte externa sigue pendiente; no se adapta el perfil a la muestra ni se relaja la validación.

## Piloto Live controlado

El titular autorizó una campaña limitada para resolver el flujo dominicano real: una verificación inicial y una recurrente. **Live y Sandbox son aplicaciones separadas**, con sus propias claves y workflows; cambiar la vista de la consola no sustituye las credenciales runtime. Se confirmó una sesión Live en «My Application» (`9ad42942-de19-482a-b1c4-7044b1956038`). «All true detection (Sandbox)» siguió devolviendo `environment=sandbox` después del cambio de vista comunicado por el titular. [Modelo oficial de entornos](https://docs.didit.me/integration/sandbox-testing#how-to-switch-between-live-and-sandbox).

La clave permanece en `.env`, nunca en argumentos, chat o Git. El piloto Live usa exclusivamente este grupo privado; la aplicación habitual conserva `DIDIT_API_KEY` y sus IDs originales:

```dotenv
DIDIT_LIVE_API_KEY=CLAVE_PRIVADA_DE_MY_APPLICATION
DIDIT_LIVE_APPLICATION_ID=9ad42942-de19-482a-b1c4-7044b1956038
DIDIT_LIVE_WORKFLOW_ID=aa2b516d-9563-46f9-adb9-0d484ca6cfaf
DIDIT_LIVE_AUTHENTICATION_WORKFLOW_ID=02d83807-8d18-4e64-af3d-e28e47581713
```

Los dos workflows nuevos se publicaron y releyeron: inicial DOM → ACTIVE_3D → Face Match, recurrente ACTIVE_3D → Face Match. No son predeterminados ni modifican los anteriores; no se crearon capturas al configurarlos. La campaña usa HTTP de ATD y un partner ficticio aprobado, en otra DB privada. No habilita el switch del negocio habitual. Cada laboratorio fija su perfil de credenciales en el marcador para que una nueva clave Live no cambie la interpretación de intentos anteriores.

Preflight sin crear solicitudes ni sesiones, para una campaña nueva (una existente conserva su perfil):

```bat
python tests/Support/api-lab/pilot.py check --live
```

Solo lee la configuración de ambos workflows; `provider_environment_confirmed=false` es esperado porque todavía no existe sesión cuya respuesta confirme Live. Rechaza comprobaciones adicionales a documento, vida activa y comparación facial 1:1. No verifica saldo ni congela la tarifa. Didit puede cobrar según comprobaciones y reintentos; consultar el precio aplicable en la cuenta, sin asumir que una sesión equivale a un importe fijo. El laboratorio limita a dos reservas diarias y no compra créditos ni activa recarga automática.

Con la clave de la aplicación Live y los tres IDs configurados, ejecutar en una terminal normal de la carpeta del proyecto:

```bat
python tests/Support/api-lab/pilot.py prepare --live
```

Pedirá `AUTORIZO LIVE`, nombre, apellidos, cédula dominicana y dirección. Los campos personales se leen de forma oculta; es normal no ver caracteres mientras se escribe. Se requiere documento real del titular y autorización de captura/retención por Didit, además de la reutilización operativa de 90 días en ATD. Una terminal sin entrada privada compatible cancela antes de crear. No compartir esos datos por chat. Los 90 días no son una política automática de borrado de Didit.

El runner fija Live y polling solo en sus procesos locales; producción ignora la excepción de polling y sigue exigiendo webhook firmado y aprobación de producción. Persiste la clave de idempotencia antes del primer POST. Confirma sesión/workflow/referencia/entorno por GET antes de entregar el enlace. Si esa confirmación falla, conserva el intento para conciliación: no volver a crear con otra clave. `diagnose` muestra entorno esperado/observado y vinculación, sin enlace, decisión bruta o datos personales. El móvil abre solo el HTTPS de Didit y no necesita acceso al localhost.

**Saldo y respuesta de creación:** antes de una captura Live con ACTIVE_3D comprobar Billing en Didit: esta comprobación no pertenece al cupo gratuito de vida pasiva. Una clave que lee workflows correctamente no demuestra saldo suficiente. El preflight de este runner no consulta Billing ni promete que se pueda crear una sesión. [Crédito y funciones de pago](https://help.didit.me/billing-credits/not-enough-credits).

El segundo intento del titular, ya con la clave Live correcta, quedó `create_uncertain` sin ID remoto. La búsqueda por su referencia no encontró sesión; el saldo observado era US$0.00 y el workflow tenía ACTIVE_3D, sin white label. La respuesta HTTP original se había descartado: no se inventa que fue un 402 ni se modifica el intento para hacerlo pasar por un rechazo confirmado. El titular indicó que agregaría saldo más adelante. El intento y sus datos cifrados se conservan; no ejecutar `new` ni volver a introducirlos.

`status` intenta conciliar una creación incierta **solo mediante GET**: exige una sesión remota única con workflow, entorno, referencia y metadata ATD coincidentes. La búsqueda no encontrada, ambigua o sin autorización conserva el bloqueo. La conciliación no reenvía POST, no crea otra orden y no necesita nuevos datos personales. Un operador debe resolver el intento antes de autorizar cualquier nueva creación; agregar saldo por sí solo no reinicia la reserva.

Los fallos nuevos conservan únicamente HTTP status, código técnico de una lista fija y si el rechazo de creación fue explícito, en auditoría privada. No guardan cuerpos de error, cabeceras, tokens o evidencia. Un 4xx explícito de la lista admitida queda `provider_rejected`; transportes, respuestas inválidas, 429 y 5xx continúan inciertos. Incluso un 502 cuyo cuerpo mencione crédito no autoriza reintentar. Ambos casos se exponen públicamente como `attention_required`, sin errores internos del proveedor.

**Recuperación del intento mal dirigido:** el primer comando Live del titular creó una sesión Sandbox, `Not Started`, porque aún usaba la clave original. Se conservan su DB y recibo; no se etiqueta como Live ni se presenta su enlace para documento real. Con `DIDIT_LIVE_*` ya preparado, ejecutar una sola vez:

```bat
python tests/Support/api-lab/pilot.py new --live
```

Antes de permitirlo, el helper consulta el intento anterior y exige vinculación confirmada, entorno real Sandbox, estado `Not Started`, ninguna captura recurrente y una aplicación diferente con perfil Live completo. Archiva el estado anterior y prepara otra DB; vuelve a pedir consentimiento y datos ocultos. Una sesión Live, iniciada, sin confirmar o de la misma aplicación bloquea la recuperación: no permite crear a ciegas ni repetir un cargo incierto. Los reintentos del nuevo intento mantienen su propia clave idempotente. No se cambia el entorno de una sesión existente.

Después de capturar documento y rostro, ejecutar `python tests/Support/api-lab/pilot.py status`. Solo cuando la conclusión inicial sea `verified`, ejecutar `python tests/Support/api-lab/pilot.py recurrent`: registra la referencia consentida y crea **otro** enlace para ACTIVE_3D + Face Match sin nueva cédula. Completarlo en el celular y consultar `status` otra vez. La segunda conclusión debe ser `verified`, `verification_kind=recurring` y `document_reverified=false` para cerrar ese recorrido externo. No se presupone que una prueba adversa vaya a aprobarse; ante rechazo/revisión se conserva y analiza el resultado.

Los comandos posteriores retoman el entorno y perfil de la campaña guardada; no hace falta repetir `--live`. Si el token ficticio existente venció, el helper local renueva únicamente sus mismos permisos tras comprobar cuenta/partner; no resucita tokens revocados ni partners suspendidos. `new` no crea otra inicial Live sobre una campaña ya creada, salvo la recuperación comprobada descrita arriba. Los procesos se detienen al terminar cada comando: no hay que abrir tres terminales ni publicar un túnel.
