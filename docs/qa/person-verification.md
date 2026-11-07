# Verificaciones por persona y capa fija del hero

Revisión del 24 de octubre de 2026. Suite local: **93 pruebas, 1.301 aserciones**, Pint, sintaxis JavaScript, validación Composer y `git diff --check` correctos. La matriz CI verifica además PHP 8.3/SQLite y PHP 8.4/MySQL; consultar los checks del commit desplegable. Las columnas cifradas nuevas usan LONGTEXT para absorber la expansión de JSON Unicode y cifrado, sin reducir los límites de entrada validados.

## Cobertura automatizada nueva

- Migración aditiva, clave foránea y ficha única por orden; no conversión de expedientes previos.
- Ficha cifrada y oculta de la serialización genérica; campos condicionales, autorización, formato de cédula y cantidad 1.
- Varios servicios para una sola persona; rechazo de claves desconocidas y de conclusiones elegidas por el cliente.
- API JSON + cargas clasificadas, aislamiento por cuenta y ausencia de ficha en el listado general. Detalle privado con evidencia pendiente, sin revelar decisiones.
- Idempotencia que incluye persona y categoría del archivo; HMAC de datos personales y reconocimiento de claves anteriores. Reintentos históricos coincidentes de API sin crear expedientes nuevos.
- Creación web y archivos atómicos: falta de evidencia deja cero órdenes/archivos huérfanos. Selfie PDF rechazada. Se conservan límites, cuotas, validación MIME y antivirus compartidos.
- Bloqueo de revisión/facturación sin evidencia, de finalización sin conclusión humana y de publicación sin pago. Conclusiones por servicio cifradas y recuperables solo por entrega autorizada/publicada.
- Prueba de conclusiones Unicode en el máximo validado, con cifrado de más de 65.535 bytes, para evitar truncamiento en MySQL.
- Portal: campos personales y conclusiones reservadas al personal, requerimientos iniciales y resultados privados. Catálogo público incluye identidad y mantiene los servicios anteriores.

## Revisión en navegador

SQLite temporal independiente con personas, cédulas, cuentas y casos ficticios. Correo array, sin cola de envíos remotos ni biometría externa. La exigencia de 2FA solo se omitió en el proceso de vista previa; no cambió la configuración del portal normal. Se cerró ese servidor y se retiró exclusivamente su base temporal; el helper permite reproducir los datos.

Escritorio y viewport de 390 × 844: selector de los tres servicios, ficha única, activación/desactivación de secciones, requisitos de cédula y selfie, límites conjuntos de archivos, ficha administrativa, evidencia pendiente y controles de conclusión por servicio. No hubo desbordamiento horizontal en móvil (375 px útiles). El envío correcto, rechazos y publicación se verificaron mediante pruebas HTTP automatizadas, sin aceptar consentimientos de personas reales en navegador.

Hero: se conserva la malla original y su `art-drift`; la imagen izquierda/superior tiene `animation-name: none` y `transform: none`. Alpha verificado tras conversión WebP. Revisión en tema claro y oscuro, escritorio y móvil; consola sin errores. Los controles y el texto mantienen su posición y accesibilidad. Capturas guardadas como evidencia local de la tarea.

No sustituye prueba en móvil físico, medición Lighthouse/Core Web Vitals en staging, validación comercial de un proveedor biométrico, revisión de retención/privacidad por ATD, ni pruebas reales de SMTP/OAuth/antivirus. No se ha contratado, conectado ni simulado como disponible una verificación biométrica automática.

En la base habitual **local** se aplicó únicamente la migración aditiva y ServiceSeeder para incorporar identidad. No se modificaron usuarios, órdenes o resultados previos. Producción no fue desplegada ni migrada.
