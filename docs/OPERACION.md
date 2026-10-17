# Operación y recuperación

## Inicio y cuentas

Ejecutar iniciar.ps1. Crear la cuenta desde /instalacion y completar MFA. El propietario de plataforma gestiona empresas y planes; esa función no concede acceso a ventas o clientes de otra empresa. El propietario comercial invita personal y asigna owner, cashier o warehouse. Revocar el último propietario se impide; dispositivos offline pendientes deben conciliarse antes de retirar al responsable.

En local, los enlaces de correo se encuentran en app/storage/logs/laravel.log. No copiar ese archivo a un repositorio. Si se pierde MFA, usar los códigos de recuperación guardados durante su configuración. No existe acceso maestro de soporte.

## Inventario y costos

Disponible = físico - reservado - bloqueado - asignado offline. La confirmación de venta consume físico y reserva juntos; la entrega cambia su estado operativo sin descontar por segunda vez. Una cancelación de reserva libera disponibilidad sin generar mercadería. Un traslado despachado está en tránsito y deja de ser físico de origen; recibir lo incorpora al destino. Las dañadas recibidas quedan físicas y bloqueadas.

El costo promedio gerencial se mantiene por empresa y variante e incluye almacenes y tránsito. Las ventas guardan snapshot; los cambios posteriores de precio/costo no reescriben ventas pasadas. Devoluciones conservan costo y descuento proporcional con redondeo acumulado. Mercadería de costo desconocido hace que el resultado se muestre parcial. No se afirma contabilidad fiscal ni utilidad neta oficial.

Cada borrador de compra corresponde a una variante. El costo unitario lo determina el responsable, incluyendo distribución gerencial de costos adicionales e impuestos no recuperables cuando proceda. El sistema no decide automáticamente el tratamiento tributario. Un reverso de recepción requiere unidades libres y pool suficiente; mantiene entrada original y movimiento compensatorio.

## Dinero

Venta, cobro y deuda son hechos separados. Una venta de S/150 con abono S/50 deja deuda S/100. Efectivo requiere caja abierta del operador. Constancias de Yape/BCP son pendientes hasta aprobación autorizada. El sistema registra la ejecución manual de un reembolso mediante referencia; no transfiere dinero por sí mismo. No reutilizar referencias ni registrar una transferencia que no se haya realizado.

Una reserva impaga vence a los 30 minutos mediante scheduler. Un pago verificado pendiente de conversión conserva la reserva para revisión. Un pago tardío no garantiza stock. Devolución comercial, mercadería recibida y reintegro de dinero se registran separadamente.

Los cupones se aplican uno por pedido. Importe fijo reemplaza porcentaje, se limita al subtotal elegible y se distribuye por proporción con céntimos acumulados exactos. Categoría/variante/canal restringen elegibilidad. El mínimo corresponde al subtotal completo. Los usos se consumen al reservar y se liberan al cancelar/vencer. Un límite por cliente exige identificación vinculada a esa empresa. No se acumulan cupones ni descuentos manuales ocultos.

## Offline

Con conexión y caja abierta, asignar unidades exclusivas al dispositivo; abrir la caja offline y protegerla con PIN. Solo se permiten ventas en efectivo dentro de asignación y plazo. No se guardan contraseñas administrativas en la PWA. La cola cifrada no sustituye una copia externa: exportar recuperación cifrada antes de cambiar/borrar navegador. Una falla de almacenamiento conserva cambios en memoria, bloquea nuevas ventas y permite exportar o reintentar guardar; no cerrar esa pestaña sin recuperación.

Al volver la conexión, iniciar sesión, sincronizar y conservar los acuses. No borrar pendientes con error. Un permiso vencido/revocado requiere revisión individual del propietario sobre la operación exacta. La conciliación exige declarar la última secuencia y sincronizar todas las ventas antes de liberar sobrantes. Un permiso vencido no libera stock automáticamente. Cerrar sesión bloquea la pantalla, no borra la cola.

## Respaldo y restauración

respaldo.ps1 cifra PostgreSQL, configuración y archivos privados. probar-restauracion.ps1 usa sas_san_test, descifra/autentica, crea una base nueva, restaura y compara conteos e aislamiento RLS. No ejecutar restauraciones sobre una base activa. La base de restauración se conserva para inspección. Llevar respaldo y backup.key a ubicaciones seguras distintas y guardar APP_KEY. La copia no cubre la cola pendiente de un navegador desconectado.

No se configuró archivado WAL/PITR ni copia remota. El tiempo de restauración medido sobre la pequeña base de pruebas no garantiza el RTO con datos productivos. Para recuperar una empresa de una base compartida: restaurar primero en entorno aislado, revisar y extraer sus datos con controles; nunca reemplazar toda producción para recuperar una sola empresa.

## Incidentes

- Error de pago: conservar referencia, caja, actor y auditoría; revisar y registrar reverso autorizado. No editar libros por SQL.
- Diferencia de stock: tomar corte, comparar movimientos y posiciones, aprobar conteo con motivo. El corte desactualizado se rechaza.
- Pérdida del equipo: revocar accesos y conciliar offline; no liberar asignaciones sin conocer las ventas pendientes.
- Fuga de secreto: revocar/rotar el secreto específico y revisar registros. No regenerar APP_KEY directamente: los datos cifrados anteriores dependen de ella.
- Docker con errores I/O: conservar disco y evidencias; solicitar recuperación especializada. No usar restablecimiento de fábrica ni borrar volúmenes.
