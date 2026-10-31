# Manual rápido del operador

## Primera instalación y acceso

En la entrega local completa ejecutar `armar-entorno-portatil.ps1` y después `iniciar.ps1`. Abrir la dirección mostrada y completar `/instalacion`. El propietario elige su contraseña y configura MFA en Seguridad; guardar sus códigos de recuperación en privado. No existen cuentas ni contraseñas universales.

Desde el catálogo público se puede explorar la tienda sin acceso de personal. El comprador tiene una cuenta separada. El propietario administra empleados y permisos; un usuario de catálogo puede cargar productos sin recibir acceso a caja.

## Cargar productos

En Catálogo, ingresar nombre y precio; puede añadirse foto y el sistema genera código si no se indicó uno. Las variantes con talla/color usan SKU independientes. En Gestionar fotos se puede subir, ordenar, describir y elegir portada. El precio se introduce en soles; internamente se conserva en céntimos.

Crear el producto no agrega existencias: recibir mercadería desde Inventario o Compras indicando almacén, unidades y costo. La carga CSV permite revisar antes de confirmar; use la plantilla disponible. Antes de importar archivos grandes, guardar respaldo.

## Vender y cobrar

1. Abrir una caja personal en el almacén autorizado e indicar el efectivo inicial.
2. Entrar en POS y buscar por nombre/SKU o leer un código previamente guardado en el producto. El lector debe actuar como teclado y enviar Enter. Revisar producto y cantidad antes de cobrar.
3. En efectivo, distinguir el importe aplicado a la venta del dinero recibido: si la venta es S/35 y se recibe S/50, el vuelto es S/15.
4. Yape/BCP se registran manualmente. El propietario verifica en su cuenta el ingreso real y registra referencia; una captura o un mensaje no acreditan por sí solos el pago. El cajero no puede autoaprobar el componente digital de un pago mixto.
5. El crédito requiere cliente identificado y límite autorizado; los abonos se registran sobre el pedido correspondiente.
6. Revisar la confirmación y abrir el documento interno para imprimir o reimprimir. La impresión no constituye una venta nueva. Se debe probar la impresora y su formato en el equipo del cliente.

Esta entrega utiliza notas de venta internas. No emite boletas/facturas electrónicas ante SUNAT ni procesa pagos bancarios automáticamente.

## Devoluciones y cierre

En el pedido, el propietario registra la devolución física/comercial con motivo y condición. La devolución al stock y el reembolso de dinero son operaciones separadas: solo registrar el reembolso después de ejecutarlo realmente. Las ventas confirmadas no se eliminan.

Al cerrar el turno contar el efectivo físico y registrar el arqueo de la caja propia. Una venta cobrada en otra caja no pertenece a ese turno. La reapertura requiere propietario, contraseña reciente y motivo auditado. Revisar diferencias y pendientes antes de cerrar el negocio.

## Reinicio y trabajo sin conexión

`iniciar.ps1` arranca la base y los servicios de esta instalación; `detener.ps1` los detiene sin borrar ventas. Antes de apagar, guardar operaciones y conciliar pendientes. `detener.ps1 -DetenerBase` también detiene el clúster de esa instalación.

Las ventas confirmadas quedan en PostgreSQL; comprobar cualquier operación interrumpida antes de reintentar. Una pestaña abierta o un formulario sin confirmar no equivalen a una venta guardada.

El modo sin conexión necesita una asignación previa de existencias, un dispositivo aceptado y su PIN local. Sincronizar al regresar la conexión y revisar los pendientes. No borrar datos del navegador ni perder su PIN con ventas pendientes; el respaldo del servidor no incluye esas ventas aún no sincronizadas.

## Respaldo y recuperación

Consultar `GUIA_RESPALDOS_Y_RECUPERACION.md`. Revisar la fecha y `resultado` en `ESTADO_ULTIMO_RESPALDO.txt` y comprobar el segundo destino. `EXITOSO` acredita esa ejecución; no garantiza protección frente a cualquier pérdida. Ensayar una restauración y conservar backup.key por separado.

Para recuperar acceso sin correo, el operador autorizado utiliza `recuperar-acceso-local.ps1` en el equipo. La contraseña se solicita de forma oculta y se cierran las sesiones previas. MFA se conserva por defecto; su limpieza exige confirmación y una nueva configuración personal. Los correos reales de invitación/recuperación necesitan un servicio configurado y probado por separado.
