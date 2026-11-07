# Selector, Resumen y facturación por modalidad

5 de noviembre de 2026 · contrato API 1.8.0.

- **Suite:** 200 pruebas y 2.255 aserciones sin fallos. Comprueba tarifas por modalidad, rechazo de precios de familia y de partidas ajenas o incompletas, cantidades, redondeo, instantáneas e historial. Mantiene permisos de facturación, pagos y entrega.
- **Laboratorio:** 515 llamadas HTTP y 1.471 comprobaciones correctas, los diez servicios y las 25 operaciones del contrato. El caso poligráfico de tres personas devuelve dos partidas con cantidades 2 y 1, precios confirmados diferentes y total conciliado. Didit y correo simulados.
- **Selector:** revisión de capturas antes/después en escritorio y a 390 × 844. Cada familia tiene cabecera y cuadrícula propias. Búsqueda sin acentos, filtro de familia, selección visible y confirmación fija; sin desbordamiento horizontal del documento en móvil. Las modalidades se eligen por persona.
- **Resumen:** se abre el diálogo con Filtros; la combinación texto/facturación actualiza los resultados y las tarjetas. Escape devuelve el foco al botón. El cliente ve cinco tarjetas, incluido saldo pendiente; el personal conserva total cobrado.
- **Facturación visual:** tres tarifas de poligrafía y cuatro de perfiles en el panel. La factura ficticia de dos unidades de Nuevo ingreso a DOP 1.250,50 y una de Rutina o control a DOP 1.850,00 totaliza DOP 4.351,00. Se comprobó también la representación imprimible y la respuesta API.
- **Actualización local:** respaldo privado consistente, integridad y claves foráneas correctas, columnas anteriores de las nueve tablas protegidas sin cambios. Las facturas anteriores conservan su desglose original. La migración no copia tarifas de las familias a sus modalidades.

Las pruebas visuales usaron SQLite temporal, usuarios y datos ficticios; no se realizaron cobros, capturas Live ni envíos de correo a clientes.
