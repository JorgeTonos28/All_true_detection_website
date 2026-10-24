# Verificación de identidad: alcance y automatización

## Disponible en esta entrega

`identity-verification` es un servicio de revisión humana, solicitado desde web o API. Comparte la persona de la orden con título y exequátur, sin duplicar sus datos. Requiere ficha autorizada y selfie; el documento de identidad es opcional si se pide solo este servicio. La ausencia de documento puede impedir llegar a una conclusión: el profesional debe indicar límites y usar `inconclusive` cuando corresponda. Una foto aportada NO constituye prueba de vida.

Los campos obligatorios, evidencia, límites y contrato de resultados están en [la guía API](api.md). Una orden por persona, cantidades 1, datos cifrados, fotos privadas, sin audio/vídeo, sin decisiones automáticas de elegibilidad. El cliente no decide ni altera la conclusión. ATD registra `verified`, `not_verified` o `inconclusive` con notas por cada servicio. Las entregas siguen exigiendo finalización, factura pagada y publicación administrativa; ningún resultado se incorpora a webhooks o correos.

El aviso de privacidad se amplió con autorización del propietario para describir cédulas, selfies y revisión humana. La declaración del solicitante se conserva con el expediente y fecha de consentimiento; no sustituye la documentación de autorización que ATD deba exigir para cada caso. No se ha contratado ningún proveedor externo ni se han transmitido imágenes a uno.

## Opciones técnicas investigadas (2026-10-24)

- **Sumsub:** su [configuración de pasos](https://docs.sumsub.com/docs/verification-steps) documenta documento de identidad y selfie con comprobación avanzada de vida, incluyendo movimiento de cabeza en WebSDK. Es una opción técnica para el recorrido que solicita ATD. La [guía de prueba de vida](https://docs.sumsub.com/docs/liveness) exige configurar el SDK y un nivel de verificación. La [cobertura documental](https://docs.sumsub.com/docs/supported-documents-and-countries) debe comprobarse para los modelos concretos de cédula dominicana en la cuenta comercial, no inferirse de una cifra global de países.
- **Veriff:** documenta verificación de documento + selfie y prueba de vida; su [guía de webhooks](https://devdocs.veriff.com/docs/webhooks-guide) diferencia productos y decisiones. El tipo de integración/plan determina el webhook y el nivel de automatización. Los callbacks están firmados y pueden repetirse o llegar fuera de orden.

No se ha confirmado precio, elegibilidad de contratación de ATD, autorización para prestar el servicio a terceros, ubicación/retención de los datos, ni cobertura del documento dominicano específico. Estas condiciones requieren validación comercial y de protección de datos antes de recomendar una contratación definitiva. No se crearon cuentas, suscripciones ni credenciales. El directorio de proveedores no estaba disponible en el entorno; la investigación se basó en documentación oficial.

## Cómo se integraría la fase automática

Esto es un diseño de la siguiente fase, **no endpoints o un adaptador desplegados**. No hay una variable que convierta el modo manual en biometría automática.

1. Elegir proveedor/plan y comprobar en sandbox el documento real admitido, calidad, idioma, accesibilidad, errores y participación de la persona. Configurar finalidades, consentimiento, retención/supresión y condiciones contractuales antes de datos reales. No basta con disponer de una API key.
2. El backend de ATD crearía una sesión por expediente/intento y entregaría un enlace o token efímero de captura. La persona realizaría el recorrido de cámara en el SDK/flujo alojado; el integrador servidor a servidor debe hacer llegar ese recorrido a la persona. Subir fotos al backend no reproduce un desafío de vida con movimiento.
3. Mantener identificador de sesión y cuenta enlazados en servidor, estados monotónicos, idempotencia y secretos en gestor de secretos. Evitar nuevas sesiones facturables ante timeouts; conciliar con el proveedor. No aceptar una decisión enviada por navegador o cliente API.
4. Recibir el webhook en una ruta específica con validación criptográfica del cuerpo original y de su vinculación a la sesión/cuenta; persistir, deduplicar y procesar en cola. Tratar reintentos, decisiones tardías, reenvíos, caducidad, petición de nueva captura y revisión manual. No registrar el payload sensible en logs. Restringir CSP/cámara a ese recorrido, sin abrir permisos globales.
5. Traducir la decisión y sus limitaciones al contrato de conclusiones. Un resultado técnico no equivale a confirmar pago ni a decidir elegibilidad de la persona para empleo, crédito u otro servicio. Los estados no concluyentes deben permitir revisión humana. Título y exequátur siguen necesitando sus propias verificaciones; una coincidencia facial no los valida.
6. Mantener la política de entrega de ATD: resultado privado hasta trabajo completado, pago total y publicación. El consumidor recibe `result.published` y recupera la entrega autenticada por API. Cambiar a publicación totalmente automática requeriría una política explícita distinta; no se ha relajado ese control.

Pruebas de aceptación de la fase futura: firma inválida, replay y callbacks fuera de orden, aislamiento entre cuentas/sesiones, documentos no admitidos, rechazo/no conclusión, sesiones vencidas, reintentos sin doble cobro, baja/revocación, supresión, resultado oculto antes del pago/publicación y caída del proveedor con derivación humana.
