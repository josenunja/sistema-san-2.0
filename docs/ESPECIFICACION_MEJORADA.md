# Plataforma multiempresa SAS SAN — especificación mejorada

Versión 2 · 17 de octubre de 2026

## Objetivo y alcance
Construir una aplicación local Laravel/Vue con tiendas independientes por empresa y gestión comercial. La especificación original se conserva en REQUISITOS_ORIGINALES.md como referencia; ninguna afirmación del documento representa trabajo completado.

## Mejoras sobre la secuencia original
- Sustituir la ejecución ciega de 24 prompts por entregas verticales verificables. Cada entrega debe tener un recorrido operativo, datos persistentes y una prueba de autorización negativa.
- Primera entrega: directorio, creación segura de empresas por consola, autenticación, membresía y catálogo público/privado. Excluir checkout hasta tener inventario y reservas probados.
- No exigir que todos los módulos tengan tablas desde el principio. Modelar y migrar al implementar cada flujo.
- Separar entorno local de infraestructura productiva; no considerar el servidor PHP de desarrollo una configuración de despliegue.
- Desactivar el registro público de personal. El alta inicial exige acceso a consola y contraseña ingresada de forma privada.
- Separar propietario de PostgreSQL y usuario de ejecución antes de probar RLS; probar lectura, escritura y limpieza del contexto sin superusuario.
- Definir precios exactos en céntimos PEN, cantidad entera y límites explícitos. Reservar fiscalidad, impuestos y redondeo tributario para una decisión documentada antes de emitir documentos reales.
- Definir devoluciones como operaciones compensatorias; jamás borrar movimientos históricos.
- Requerir evidencia para cada integración. Yape y BCP comienzan como instrucciones de pago manual y constancias pendientes; WhatsApp comienza como enlace, SUNAT deshabilitado.
- No activar offline hasta probar asignación exclusiva de unidades, idempotencia y recuperación de pendientes.

## Entregas y aceptación
1. Fundación: acceso, empresa y catálogo. Producto creado por A aparece en su tienda si se publica; B no puede gestionarlo; rol SQL restringido no puede leer sin contexto.
2. Inventario: almacenes, recepciones con costo, movimientos, reserva y transferencia; última unidad probada con concurrencia real.
3. Comercio: carrito, promociones, pedido idempotente, pago manual y seguimiento de invitado; total recalculado por servidor.
4. POS y crédito: caja, venta, abonos, deuda y devoluciones; libro de cobros conciliable.
5. Operación: CRM, informes, editor de tienda, imágenes e importación con validación.
6. SaaS: planes, soporte auditado y MFA administrativo obligatorio.
7. Offline: dispositivo, permiso limitado, asignación y cola recuperable; replay con efecto único.
8. Preparación productiva: hardening, CI, backup y restauración medidos, aceptación A/B, conexión sandbox fiscal si existe proveedor.

## Condición de cierre
Cada entrega registra implementado, probado, fallido o pendiente en PROGRESS.md, con evidencia observable. Producción requiere aceptación completa, MFA obligatorio, recuperación ensayada y configuración verificada. Los 24 pasos originales conservan valor como lista de cobertura, no como garantía de terminación.
