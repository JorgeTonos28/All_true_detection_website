# Solicitudes por personas y vistas previas

Fecha de revisión: 26 de octubre de 2026 (America/Santo_Domingo).

## Alcance automatizado

- Esquema con varias personas por orden, perfiles cifrados y FK compuesta que impide adjuntos cruzados entre órdenes.
- Migración de una instalación con datos anteriores, preservando literalmente los perfiles cifrados, requisitos originales y documentos; sin usar la conexión configurada en `.env`.
- Asignación de servicios, cantidades correspondientes, campos pertinentes, rechazo de cédulas innecesarias y autorizaciones obligatorias.
- Detalles omitidos, nulos y cortos; capacidad para 10.000 caracteres Unicode cifrados sin truncamiento.
- API `people`, identificadores públicos, listas sin perfiles personales, límites, entradas malformadas, aislamiento, scopes y reintentos con orden estable de personas.
- Título y exequátur como documentos clasificados obligatorios, no sustituidos por apoyo; persistencia atómica y retirada de archivos fallidos.
- Duplicados exactos de contenido, incluidos archivos renombrados; el reintento con la misma clave de adjunto conserva el archivo anterior.
- Vistas previas privadas de imágenes web/API; rechazo de PDF como imagen; pago/finalización/publicación obligatorios también para la vista previa de resultados.
- Renderizado de fichas y conclusiones de administración por persona; menú Solicitudes activo en creación y detalle.
- Los dos ejemplos JSON y el cuerpo JSON del comando CMD de la guía se ejecutan contra la aplicación en pruebas.

## Prueba interactiva en navegador

Base SQLite temporal independiente y credenciales aleatorias de `tests/Support/preview-portal.php`; transporte de correo array, sin SMTP, webhooks ni proveedor biométrico reales. Las imágenes se generaron con `tests/Support/preview-documents.php` y dicen expresamente «DOCUMENTO FICTICIO - SOLO PRUEBAS».

Se completó el envío único de dos personas con servicios diferentes: una con antidoping y psicometría, otra con psicometría. La solicitud se creó sin detalles ni cédula, y las partidas mostraron cantidades 1 y 2 correctamente. Se comprobó creación de fichas en modal y conservación al cerrarlas.

Se completó un segundo caso con título y exequátur: datos ficticios, campos documentales, miniaturas antes de enviar y corrección de un frente seleccionado también como reverso. El formulario bloqueó la copia repetida antes de enviarla. Tras seleccionar un reverso distinto, se enviaron cuatro archivos y se verificaron la galería, las categorías/persona y la ampliación de una imagen ya almacenada sin descargarla.

Responsive en 1440 × 1000 y 390 × 844. Se corrigió el doble scroll del selector; en móvil, el botón Confirmar permaneció dentro del viewport (borde inferior 783 de 844 px). La página midió 390 px de ancho y el modal de imagen 352 px, sin desplazamiento horizontal. Es emulación en navegador, no una prueba en teléfono físico ni una certificación completa de accesibilidad.

Evidencias: [selector escritorio](request-services-desktop.png), [selector móvil](request-services-mobile.png) y [vista previa privada](request-image-preview.png).

## Límites de la validación

SHA-256 detecta archivos idénticos, no imágenes recortadas/reexportadas ni autenticidad documental. Las selfies siguen sujetas a revisión humana, no a biometría automática. PDF/Word se descargan; solo JPG/PNG/WebP tienen miniaturas. El formulario requiere JavaScript y, tras un error de servidor, informa que los archivos deben seleccionarse otra vez.

No se desplegó producción ni se alteraron usuarios, órdenes o archivos operativos para esta revisión. Las pruebas de actualización, ejemplos y seguridad usan bases aisladas.

Validación final local: 112 pruebas y 1.531 aserciones, Pint y `composer validate --strict` correctos; `composer audit` sin avisos de vulnerabilidad; `npm run build` correcto. Vite mantiene el aviso previo de la dependencia opcional `fontaine`, sin impedir el build.

La ampliación se aplicó después a la SQLite local habitual, exclusivamente tras confirmar `APP_ENV=local`, ubicación dentro del proyecto y que no existían otras migraciones pendientes. Se generó y verificó antes una copia consistente con SQLite `VACUUM INTO` en `storage/app/private/operational-backups/`, excluida de Git. Los conteos de usuarios, cuentas, órdenes, personas, adjuntos, resultados, facturas y pagos permanecieron iguales. No se promovieron usuarios ni se alteraron credenciales o correo.

Se detectaron límites CLI locales de PHP de 2 MB/8 MB. `composer run dev` ahora usa `portal:serve`: el comando pasa los límites a su proceso HTTP hijo, conservando la implementación del servidor de Laravel. Se probó el arranque real en el puerto aislado 8077 y respuesta HTTP 200 de login, además de verificar los argumentos y el bloqueo del comando en producción. La configuración global de PHP no se cambió. Se requiere reiniciar el proceso de desarrollo para aplicar los límites.

Se cerró la pestaña de QA, se restauró el viewport y se detuvieron los servidores temporales. Se retiraron exclusivamente la SQLite de prueba, sus cuatro adjuntos sintéticos y cinco imágenes fuente temporales. Son recreables mediante los helpers; se conservan las capturas versionadas y el respaldo privado de la base local.
