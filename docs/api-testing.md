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

## Captura externa desde el celular

El piloto externo usa otra base separada y la clave Didit Sandbox de `.env`. No ejecuta los casos sintéticos ni modifica el modo del negocio habitual. A diferencia del runner anterior, **sí crea una sesión real de Sandbox**, mediante la API HTTP de ATD y sus acciones de integración, para que el titular abra el enlace HTTPS privado de Didit. La primera preparación persiste las claves de idempotencia antes de enviar y los reintentos recuperan la misma solicitud/sesión.

```bat
python tests/Support/api-lab/pilot.py prepare
```

La pantalla alojada puede abrirse desde Safari/Chrome móvil sin exponer localhost. Usar los documentos de muestra de Sandbox, seleccionar escenario y seguir la captura ACTIVE_3D. El veredicto es simulado, pero Didit conserva los medios capturados realmente. La prueba no acredita precisión biométrica ni verifica un documento real. El usuario realiza la captura y concede cámara desde su dispositivo; el agente no maneja su cámara.

Tras terminar, consultar desde la computadora:

```bat
python tests/Support/api-lab/pilot.py status
```

El comando concilia por la API de servidor y muestra solo estado/conclusión mínima. Para solicitar la captura recurrente, una vez aprobada y vinculada la inicial:

```bat
python tests/Support/api-lab/pilot.py recurrent
```

No se crea la sesión recurrente si la inicial no está `verified`; el mock documental puede devolver una cédula diferente de la DOM ficticia solicitada. Ese caso queda no concluyente sin alterar controles. El enlace y los resultados se guardan únicamente en el laboratorio privado; `active-pilot.json` apunta a ese entorno y permite retomar el ensayo. No borrar ese archivo ni repetir con otra clave ante un POST incierto: conciliar la sesión existente.

`python tests/Support/api-lab/pilot.py diagnose` muestra únicamente estados de comprobaciones, método activo, país del documento e indicadores booleanos de formato/coincidencia; no imprime números de cédula, imágenes, puntuaciones biométricas ni respuesta bruta. Si se necesita **otra** captura inicial independiente, ejecutar explícitamente `python tests/Support/api-lab/pilot.py new`: conserva el estado previo en su carpeta privada y prepara otra base/sesión de Sandbox. No usar `new` para resolver una creación incierta o eludir límites. En el selector de Didit se pueden ensayar aprobación, rechazo y revisión; los resultados de Sandbox son simulados y no miden precisión o detección real de impostores.
