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

## Recorrido del administrador para crear una tienda

1. Entrar en /login. Si no existe ninguna cuenta, el primer administrador debe crearla en /instalacion y activar MFA en Seguridad. No hay usuario o contraseña universal.
2. Desde /dashboard, abrir Administrar plataforma y confirmar la contraseña antes de rellenar el alta. Indicar nombre, identificador sin espacios, plan y correo del propietario.
3. En este entorno, recuperar el enlace privado del registro de correo local; no se envían mensajes reales. Compartir únicamente el enlace de la invitación con su destinatario, nunca el archivo de registro completo ni contraseñas. Para entrega automática se debe configurar correo real en un despliegue autorizado.
4. El propietario acepta el enlace vigente (48 horas), elige su contraseña o utiliza la de su cuenta existente y configura MFA. Una cuenta de comprador y una membresía del personal son accesos diferentes.
5. El propietario abre Configurar tienda, publica su identidad y entra a Catálogo para crear productos: SKU único, nombre, precio, publicación, categoría, sección, talla/color y fotos. En Inventario crea el almacén y registra mercadería con costo, proveedor y referencia; publicar el producto por sí solo no crea stock.
6. Para delegar operación, entra a Mi tienda y envía invitaciones como owner, cashier o warehouse. El cajero trabaja caja/POS/clientes; almacén trabaja existencias; el propietario administra catálogo y configuración. La revocación conserva el historial y no permite retirar al último propietario.
7. El administrador global puede cambiar plan o suspender/reactivar empresas. Para subir productos a una tienda ajena necesita además una invitación con rol owner de esa empresa; no existe acceso comercial maestro. La cuenta inicial sí posee su primera empresa.

La demostración en localhost:8002 usa datos sintéticos. El trabajo real permanece en localhost:8000; no utilizar las cuentas de demostración como credenciales reales.

## Reenviar invitaciones y ayudar al cliente con sus productos

En Plataforma, cada tarjeta de tienda muestra sus invitaciones recientes y permite reenviar o revocar las pendientes/vencidas. Reenviar crea un enlace nuevo, invalida el anterior y renueva 48 horas; esperar un minuto entre envíos. Revocar requiere confirmación y no retira una membresía ya aceptada. Los propietarios disponen de las mismas acciones para su tienda en Mi tienda / Mi equipo. El correo sigue siendo local por registro, sin envíos reales.

Para cargar productos de un cliente, el superadministrador marca También cargaré productos para este cliente al crear la empresa, o pulsa Habilitar mi permiso de catálogo en una tienda existente. Después abre Cargar productos. El permiso Solo catálogo permite productos, fotos y CSV, consume un cupo del plan y no abre ventas, clientes ni caja. El propietario conserva su acceso completo y puede retirar esta ayuda desde Mi equipo. Retirar mi permiso de catálogo afecta únicamente la membresía de ayuda del administrador.

En Catálogo, completar Nombre del producto y Precio en soles. La foto es opcional y se guarda junto con el producto; el código se genera automáticamente si no se escribe. Marcar Mostrar en la tienda pública para publicarlo. Más opciones contiene código propio, categoría, talla/color, descripción y promociones. Las cargas CSV y fotos adicionales están en Importar varios productos o añadir más fotos. Crear/publicar no agrega existencias: el propietario o almacén las registra en Inventario con costo y referencia.

Para consultar invitaciones antiguas, abre Ver todo el historial de invitaciones. La vista completa tiene páginas de 25 registros y las mismas acciones de reenvío y revocación.
