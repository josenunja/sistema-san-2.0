# SAS SAN

## Manual completo de instalación, administración y operación

Edición local Windows | Una empresa por instalación | 31 de octubre de 2026

Guía para el propietario, sus empleados y el técnico de entrega. Describe la versión local con acceso guiado a la tienda publicada el 31/10/2026. Los ejemplos son ficticios y no contienen credenciales. Conserve este documento junto con el acta de entrega, sin adjuntar claves ni datos privados de sus clientes.

## 1. Alcance de esta entrega

SAS SAN permite administrar una tienda con catálogo público, productos y fotos, inventario, compras y proveedores, ventas, caja, clientes, crédito, promociones, entregas y empleados. Esta entrega se vende como una instalación independiente por negocio. El propietario controla su información y el acceso de su equipo.

El sistema trabaja con precios en soles y almacena los importes en céntimos. Por ejemplo, S/35.50 corresponde a 3550 céntimos internamente. Las cantidades de productos son enteras. La interfaz muestra fechas de operación según la configuración de Lima; algunos registros técnicos y respaldos identifican su fecha en UTC.

| Incluido en la instalación local | Requiere contratación o preparación adicional |
| --- | --- |
| Catálogo, inventario, compras, ventas y caja | Dominio y alojamiento público |
| Notas de venta internas | Boletas/facturas electrónicas y proveedor fiscal |
| Registro manual de pagos Yape/BCP | Pasarela o conciliación bancaria automática |
| Invitaciones y recuperación local asistida | Correo real para invitaciones y recuperación remota |
| Respaldo cifrado y restauración en base nueva | Destino externo, custodia y política de retención |
| Caja sin conexión con preparación previa | Aceptación de cada equipo, navegador y periférico |

Elegir el rubro ropa, panadería o mecánica adapta la presentación. No incorpora recetas de fabricación, control por peso fraccionado ni órdenes especializadas de taller. Los tickets de esta entrega son documentos internos; no acreditan emisión ante SUNAT.

La publicación del código en GitHub no equivale a una tienda alojada en Internet. La dirección localhost funciona en el equipo donde se ejecuta el programa. La puesta en servicio comercial requiere probar la computadora del cliente, capacitar a sus operadores y aceptar sus respaldos y periféricos.

## 2. Ruta recomendada para empezar

Prepare el negocio en este orden. Cada etapa depende de datos de la anterior:

1. Instalar la copia completa e iniciar sus servicios.
2. Crear personalmente la cuenta del propietario y activar la verificación en dos pasos.
3. Configurar nombre, marca, contacto, horarios y métodos de entrega de la tienda.
4. Crear los almacenes que realmente se utilizarán.
5. Crear productos con precios y, si corresponde, variantes y fotos.
6. Registrar la mercadería inicial con su cantidad y costo.
7. Invitar al personal y asignar únicamente los permisos necesarios.
8. Probar una venta, su documento interno, una devolución y el cierre de caja en una copia de práctica.
9. Ejecutar un respaldo y verificar una restauración separada.
10. Publicar la configuración de la tienda y registrar la aceptación del cliente.

El catálogo, la existencia y el dinero son registros distintos. Crear un producto no agrega stock; recibir mercadería no cobra una venta; una devolución de producto no ejecuta por sí sola un reembolso de dinero. Esta separación ayuda a comprobar lo que ocurrió realmente.

Para trabajar cada día, inicie el sistema, compruebe los pendientes, abra su caja personal si va a cobrar, registre las operaciones y cierre el turno con un conteo físico. Antes de apagar, sincronice ventas sin conexión y revise el respaldo. Encontrará esos procedimientos en los capítulos siguientes.

Los nombres destacados, como **Catálogo** o **Mi caja**, corresponden a opciones de pantalla. Algunas acciones solo aparecen según el rol. Si falta una opción, revise su acceso con el propietario antes de intentar una operación desde otra cuenta.

## 3. Instalación local en Windows

Utilice el paquete completo de la entrega local, que incluye PHP, PostgreSQL, dependencias y recursos visuales compilados. El archivo automático llamado Source code de GitHub contiene el repositorio y necesita preparación técnica adicional. Para operar el paquete completo no necesita instalar Node ni Docker.

Entrega de referencia: https://github.com/josenunja/sistema-san-2.0/releases/tag/entrega-local-2026-10-31-acceso-tienda

1. Descargue el ZIP completo y su archivo de comprobación SHA-256.
2. Compruebe el hash con el archivo publicado. El técnico puede usar `Get-FileHash -Algorithm SHA256` sobre el ZIP descargado.
3. Extraiga todo el contenido en una carpeta propia del cliente, con permisos de escritura. No ejecute los scripts dentro del ZIP.
4. Abra PowerShell en esa carpeta. Mantenga la estructura de archivos incluida.
5. Prepare el entorno y después inicie el programa:

```powershell
.\armar-entorno-portatil.ps1
.\iniciar.ps1
```

La preparación crea una base nueva y claves propias para esa instalación. No distribuye usuarios ni contraseñas universales. Si ya existe configuración, la conserva y le indica que utilice el inicio habitual. No elimine archivos privados para forzar una nueva instalación.

Los puertos predeterminados son 8000 para la web y 55432 para PostgreSQL. Si están ocupados, antes de crear la instalación nueva puede elegir otros:

```powershell
.\armar-entorno-portatil.ps1 -PuertoWeb 8001 -PuertoPostgres 55433
```

Use posteriormente la dirección mostrada por el script. Compruebe compatibilidad de Windows de 64 bits, ejecución de los binarios, espacio disponible y permisos en el equipo real. No hay una capacidad de usuarios o volumen garantizada por el simple hecho de instalar.

Si Windows impide ejecutar un script descargado, el técnico debe verificar su origen y aplicar la autorización local correspondiente. No cambie globalmente las políticas de seguridad sin revisar el motivo. Si faltan binarios o dependencias, vuelva al paquete completo en lugar de copiar archivos privados de otra empresa.

## 4. Crear la cuenta y entrar a la tienda

Abra la dirección indicada por el inicio, normalmente http://localhost:8000. El catálogo público y el acceso administrativo cumplen funciones diferentes. Para la primera configuración, visite http://localhost:8000/instalacion usando el puerto de su copia.

Complete su nombre, correo, contraseña y confirmación, además del nombre, identificador y rubro del negocio. La contraseña debe tener al menos 12 caracteres. El identificador de tienda debe coincidir con la copia preparada cuando el técnico lo haya establecido previamente. No escriba una contraseña que vaya a entregar a otros empleados.

La instalación inicial se cierra después de crear el primer usuario. Si ya existe una cuenta, entre por **Iniciar sesión**. No es necesario volver a instalar el programa para acceder a su negocio.

### Si después del registro aparece Configuración de perfil

Esa pantalla permite modificar nombre y correo; la administración del negocio exige además completar la seguridad de la cuenta. En Perfil y Seguridad aparece una tarjeta de acceso al negocio:

1. Pulse **Ver mi tienda** para abrir el catálogo público. Puede hacerlo antes de completar el segundo factor.
2. Pulse **Configurar seguridad** para activar la verificación en dos pasos.
3. Una vez confirmada, use **Entrar a mi negocio** o el acceso **Mi negocio** del menú.

El menú personal reúne los accesos a su espacio, negocio, tienda pública y seguridad. Si sigue viendo una interfaz anterior después de actualizar el programa, recargue con Ctrl+F5. No borre el almacenamiento del navegador si tiene ventas sin conexión pendientes.

Una cuenta de comprador creada en la tienda pública no concede permisos de empleado. Para administrar productos o caja debe existir una membresía otorgada por el propietario. Si se registró como comprador, solicite al propietario la invitación correcta con su correo.

## 5. Seguridad de la cuenta y recuperación

Abra **Seguridad** y localice **Verificación en dos pasos**. Cuando el sistema solicite confirmar su contraseña, escriba la contraseña que usted eligió. No hay una clave de administrador válida para todas las instalaciones.

1. Pulse **Activar verificación en dos pasos**.
2. Abra una aplicación autenticadora en su teléfono y agregue la cuenta mediante el QR o la clave manual.
3. Pulse **Continuar** cuando corresponda.
4. Introduzca el código temporal de seis dígitos y pulse **Confirmar**.
5. Guarde los códigos de recuperación en un lugar privado, protegido y separado del equipo de ventas.

No comparta el QR, la clave manual ni los códigos de recuperación. Cada empleado debe usar su propia cuenta y autenticador. Si un código temporal es rechazado, compruebe que seleccionó la cuenta correcta, que no está reutilizando un código consumido y que el teléfono tiene hora automática.

Si pierde el teléfono, utilice un código de recuperación disponible. Si tampoco puede acceder y no hay correo real configurado, el técnico autorizado puede recuperar una instalación local desde su consola:

```powershell
.\recuperar-acceso-local.ps1 -Modo Nativo -Email 'propietario@example.test'
```

El correo es un ejemplo: sustitúyalo por la cuenta que desea recuperar. La herramienta solicita de forma oculta una contraseña nueva y su confirmación, y cierra las sesiones anteriores. Conserva el segundo factor por defecto. La opción técnica `-Limpiar2FA` requiere confirmación adicional y una nueva configuración personal; no debe usarse como acceso habitual.

El correo de recuperación web solo llegará cuando un servicio de correo real esté conectado y probado. En el entorno local los mensajes quedan en un registro privado. No comparta ese registro completo: puede contener enlaces sensibles de distintas cuentas.

## 6. Menús y permisos

El propietario administra la tienda y su equipo. Los demás roles reciben funciones concretas, con restricciones adicionales de almacén cuando se configuren. La cuenta del comprador se limita a su experiencia de compra.

| Pantalla o función | Propietario | Caja | Almacén | Solo catálogo |
| --- | --- | --- | --- | --- |
| Resumen y actividad | Sí | No | No | No |
| Productos, fotos y CSV | Sí | No | No | Sí |
| Inventario | Sí | No | Sí | No |
| Compras y proveedores | Sí | No | No | No |
| Pedidos y ventas | Sí | Sí | No | No |
| Punto de venta y caja propia | Sí | Sí | No | No |
| Caja sin conexión | Sí | Sí | No | No |
| Clientes | Sí | Sí | No | No |
| Autorizar crédito | Sí | No | No | No |
| Promociones, tienda y equipo | Sí | No | No | No |

Las acciones sensibles pueden exigir contraseña reciente aunque la sesión siga abierta. El rol Caja no autoriza automáticamente una verificación digital reservada al propietario. El personal de almacén opera dentro de su alcance; determinadas tareas estructurales requieren al propietario.

Puede asignar todos los almacenes, algunos seleccionados o ninguno. Ninguno significa que no podrá operar sobre existencias de almacenes. Los cambios de permisos requieren cerrar la caja afectada y conciliar sus dispositivos sin conexión cuando corresponda.

No cambie el rol para resolver un problema de stock o de caja: primero compruebe la causa. Revise periódicamente qué personas conservan acceso, retire las cuentas que ya no trabajan en el negocio y mantenga al menos un propietario válido.

La entrega habitual utiliza una empresa por copia. La consola de plataforma corresponde a otra modalidad y no aparece habilitada en esta instalación. Un administrador de infraestructura no recibe por ello acceso automático al catálogo o a las ventas del negocio.

## 7. Invitar empleados, reenviar y revocar

Entre como propietario en **Mi tienda**, sección **Mi equipo**. Use **Correo a invitar**, seleccione el rol y pulse **Crear invitación**. Para una persona que únicamente cargará productos, elija **Solo catálogo**. Para cobrar, seleccione Caja y revise su alcance de almacenes.

La persona invitada debe abrir su enlace privado, identificarse o crear su cuenta según la pantalla y completar la verificación en dos pasos antes de administrar. El propietario no necesita conocer su contraseña.

Cada invitación vence a las 48 horas. Si no se aceptó:

1. Busque el correo en la lista de invitaciones.
2. Compruebe si está pendiente, vencida, aceptada o revocada.
3. Use **Reenviar invitación** cuando corresponda; debe transcurrir al menos un minuto entre envíos.
4. Entregue únicamente el enlace nuevo por el canal privado acordado. Reenviar invalida el enlace anterior.

Para cancelar una invitación todavía no aceptada, pulse **Revocar invitación** y confirme la revocación. Se conserva el historial. Si ya fue aceptada, retire la membresía con **Revocar acceso**: revocar el correo de invitación no retira un acceso ya concedido.

La vista resumida muestra invitaciones recientes. **Ver todo el historial de invitaciones** abre la lista paginada para consultar registros anteriores. No se puede retirar al último propietario del negocio.

El entorno local no envía correos reales. Mientras no se conecte SMTP, el técnico autorizado debe obtener únicamente el enlace solicitado del registro privado y entregarlo al destinatario correcto. Antes de prometer invitaciones autónomas por correo, pruebe el envío real y su recepción.

En **Capacidad de tu instalación** se consulta el uso. La configuración inicial contempla 5000 productos, 10 almacenes y 25 miembros; las invitaciones pendientes también pueden consumir cupo. Son límites configurados, no una medición de rendimiento ni una capacidad ilimitada.

## 8. Diseñar y publicar la tienda

En **Mi tienda**, abra **Configurar tienda**. El asistente organiza la configuración de identidad y presentación. Prepare un logo, una portada, el nombre comercial y datos de contacto propios del negocio.

### Marca y apariencia

Complete el nombre, rubro, frase de presentación y descripción. Cargue imágenes adecuadas al espacio mostrado y compruebe su legibilidad en teléfono. Seleccione uno de los estilos disponibles y ajuste sus colores. Los estilos comparten las funciones del catálogo; cambiar de estilo no crea un módulo especializado del rubro.

Ordene las secciones y revise tanto la vista de escritorio como la móvil. Utilice un contraste suficiente y textos que expliquen qué vende y cómo comprar. Un precio anterior debe corresponder a la información comercial que el negocio pueda respaldar.

### Contacto y funcionamiento

Complete contacto, ubicación y redes sociales. Compruebe que el número de WhatsApp y sus enlaces abren el destino correcto. WhatsApp abre una conversación que el usuario decide enviar; el sistema no envía mensajes automáticos mediante API.

Defina días y horarios de atención, cierres y métodos de entrega. Active solo las opciones que realmente atenderá: recojo, delivery, courier o envío nacional, según su operación. Revise las tarifas antes de publicar. Si necesita zonas y franjas, configúrelas después como explica el capítulo de entregas.

Escribir un dominio en el formulario no compra el dominio ni configura DNS, certificados o alojamiento. El técnico debe preparar y validar esos servicios por separado.

### Guardado y publicación

Puede guardar un borrador y revisar una vista previa privada antes de publicar. Confirme la publicación cuando los datos estén listos y abra **Tu tienda pública** para revisar el resultado como visitante. Si algo salió mal, consulte **Versiones de mi tienda** y restaure una versión anterior de la configuración visual.

Restaurar una versión del diseño no revierte productos, ventas, dinero ni existencias. Después de cualquier publicación, vuelva a revisar el contacto, los métodos de entrega y los importes visibles.

## 9. Crear y mantener productos

Entre en **Catálogo**. La carga sencilla pide **Nombre del producto** y precio en soles; puede añadir una foto. Si deja el código vacío, el sistema genera un SKU. Marque **Mostrar en la tienda pública** cuando quiera publicarlo y pulse **Guardar producto**.

Para ampliar la información abra **Más opciones**. Puede registrar SKU, categoría, grupo de variantes, talla, color, descripción, precio anterior, sección de público, tendencia, código de barras y stock mínimo según los campos disponibles. El código de barras debe estar guardado en ese producto para que el POS pueda reconocerlo.

Cada combinación que necesite existencias propias utiliza un SKU diferente. Por ejemplo, una camiseta azul talla M y otra talla L son dos variantes; el grupo común permite relacionarlas. No use un solo SKU para tallas que desea contar por separado.

| Campo | Recomendación práctica |
| --- | --- |
| Nombre | Describa el artículo de forma reconocible |
| SKU | Código único por negocio, por ejemplo CAM-AZ-M |
| Precio | Importe de venta en soles, con sus decimales |
| Categoría y grupo | Clasificación y relación de variantes |
| Stock mínimo | Umbral de aviso, no cantidad inicial |
| Publicado | Visibilidad comercial del artículo |
| Archivado | Retiro de nuevas operaciones sin borrar historia |

El SKU admite letras, números, punto, guion y guion bajo, hasta 64 caracteres. Evite cambiar códigos usados en etiquetas físicas sin un plan de reemplazo. Un precio anterior de comparación debe ser mayor al precio actual para esa presentación promocional.

Después de guardar, registre las existencias en Inventario o Compras. El formulario del catálogo no inventa unidades disponibles. Busque el producto por nombre o código si no aparece en la página actual; el orden administrativo y el de la tienda pública pueden ser distintos.

Archivar un producto conserva su historial y lo retira de nuevas operaciones habituales. Revise las asignaciones sin conexión antes de retirarlo definitivamente de la venta, porque un dispositivo autorizado puede conservar una instantánea previa.

## 10. Fotos e importación por CSV

### Galería de producto

Abra **Gestionar fotos**, seleccione una imagen y pulse **Subir foto**. Se admiten hasta ocho fotos por producto. Puede añadir una descripción de accesibilidad, ordenar las imágenes y elegir la portada; confirme con **Guardar**. Revise la portada en el catálogo público.

Las fotos de producto admiten JPEG, PNG o WEBP, con un límite de 3 MB por archivo. No deben superar 4000 píxeles en su dimensión mayor ni 10 megapíxeles en total. El sistema procesa las imágenes y las convierte a WEBP. Si falla una carga, reduzca resolución y tamaño antes de repetir; cambiar la extensión no convierte el archivo.

Eliminar una foto no elimina el producto ni sus ventas. Compruebe el artículo y la imagen antes de confirmar. Los archivos de constancias de pago tienen acceso privado y no deben reutilizarse como imágenes públicas del catálogo.

### Cargar muchos productos

Abra **Importar varios productos o añadir más fotos** y utilice la plantilla disponible. El CSV de productos acepta estos encabezados:

```text
sku,nombre,precio,categoria,grupo,publicado
```

También puede incluir talla y color:

```csv
sku,nombre,precio,categoria,grupo,talla,color,publicado
CAM-AZ-M,Camiseta azul M,35.00,Camisetas,Modelo A,M,Azul,1
CAM-AZ-L,Camiseta azul L,35.00,Camisetas,Modelo A,L,Azul,1
```

Use punto para el decimal del precio, 1 para publicado y 0 para oculto. Guarde el archivo como CSV compatible con texto UTF-8. Si un dato contiene una coma, su editor debe encerrarlo entre comillas correctamente. No cambie los nombres de las columnas.

El archivo tiene límite de 5 MB y hasta 2000 filas de productos. La vista previa valida el contenido sin crear productos. Corrija los errores y confirme con **Confirmar importación**; la autorización de la vista previa vence aproximadamente en una hora. Si caduca, vuelva a revisar el archivo.

La importación no carga fotos, costos ni stock. Compruebe el resultado antes de repetirla. La exportación CSV está limitada a 10 000 filas y protege celdas frente a fórmulas; un archivo exportado puede requerir adaptación antes de usarlo como plantilla de importación.

## 11. Almacenes y existencias

En **Inventario**, el propietario prepara los almacenes que realmente utilizará. Identifique claramente cada ubicación para evitar recibir o vender desde otra. El personal con rol Almacén trabaja dentro de las ubicaciones autorizadas.

Para registrar una entrada use **Recibir mercadería**. Seleccione almacén y producto e indique unidades, costo unitario, proveedor y referencia. Complete todos los campos solicitados. El costo es cuánto cuesta adquirir la unidad; el precio del catálogo es cuánto cobra al venderla. No deben confundirse.

### Cómo interpretar las cantidades

| Concepto | Significado |
| --- | --- |
| Físico | Unidades registradas en el almacén |
| Reservado | Unidades comprometidas en pedidos pendientes |
| Bloqueado | Unidades que no deben venderse, por ejemplo dañadas |
| Asignado offline | Unidades reservadas exclusivamente para un dispositivo |
| Disponible | Físico menos reservas, bloqueos y asignación offline |

Ejemplo: 20 físicas, 3 reservadas, 2 bloqueadas y 5 asignadas offline dejan 10 disponibles. Una unidad en traslado aún no es existencia disponible en el destino.

Use **Enviar traslado** para el origen y después **Recibir traslado** en el destino, con la cantidad recibida. Si hay unidades dañadas, indique cuántas deben quedar bloqueadas. No registre otra recepción de mercadería para simular el mismo traslado: duplicaría existencias.

En **Bloquear / desbloquear**, seleccione la operación y su motivo. Para una diferencia de inventario use **Aprobar conteo físico**: revise la existencia al corte, introduzca las unidades contadas y documente el motivo. Si hubo movimientos desde el corte, actualice la pantalla y repita la revisión; no fuerce una cifra antigua.

Los movimientos conservan historial. Una corrección debe explicar su causa y usar la operación correspondiente. El costo promedio y los márgenes dependen de costos correctamente registrados; las ventas pasadas conservan sus datos y no se recalculan simplemente al cambiar el precio del producto.

## 12. Compras y proveedores

El propietario entra en **Compras y proveedores**. Primero use **Crear proveedor** para registrar nombre, documento o referencia y contacto. Mantenga referencias que permitan localizar el documento de compra real.

Para una compra con varios artículos, abra **Nueva compra con varios productos**:

1. Seleccione proveedor y almacén receptor.
2. Registre la referencia del documento.
3. Agregue cada producto con su cantidad y costo unitario.
4. Revise descuentos y costos adicionales del documento, si existen.
5. Guarde el borrador y compruebe sus importes.
6. Use **Confirmar compra** cuando la compra esté aceptada.
7. Registre la recepción cuando la mercadería llegue realmente.

Confirmar una compra no significa que todas las unidades ya estén físicamente en el almacén. Utilice **Recepción parcial** si el proveedor entrega una parte y registre el resto cuando llegue. Solo las unidades recibidas se incorporan a las existencias.

La pantalla conserva también el flujo de borrador de una sola variante. Lea el número de compra y el de recepción de la lista antes de operar: ciertos formularios solicitan esos identificadores. En compras de varias líneas pueden generarse registros o lotes asociados; revise cada uno en su contexto.

Si no llegará el saldo pendiente, use **Cerrar saldo pendiente** con la justificación correspondiente. Esto no elimina lo ya recibido. Si una recepción se registró por error, utilice **Revertir recepción** con compra, recepción y motivo. El sistema exige que las unidades necesarias puedan revertirse; no puede retirar mercadería ya comprometida como si nunca hubiera existido.

El costo adicional y el descuento se distribuyen conservando los céntimos del total. Revise la suma del documento, las cantidades recibidas y las referencias. El registro de una compra y su costo no sustituye un sistema contable o una declaración tributaria.

## 13. Abrir caja y vender en el POS

Cada operador cobra en su propia sesión. Entre en **Mi caja**, pulse **Abrir mi caja**, seleccione un almacén autorizado e indique el efectivo inicial contado. El dinero inicial es un fondo de trabajo, no una venta.

Después abra **Punto de venta**:

1. Busque por nombre, SKU o código de barras guardado.
2. Agregue el artículo correcto y revise variante y cantidad entera.
3. Compruebe precios, disponibilidad y total del carrito.
4. Seleccione el cliente o complete los datos que pida el formulario.
5. Indique cómo se pagará y revise los importes aplicados.
6. Pulse **Confirmar venta** una sola vez y espere la confirmación.
7. Abra el documento interno para entregar o imprimir el comprobante operativo.

El lector de códigos debe funcionar como teclado y enviar Enter. Pruebe que el código identifica la variante correcta antes de utilizarlo durante la atención. Una etiqueta ilegible no debe resolverse cobrando un producto similar.

Para una venta de S/35 pagada con S/50 en efectivo, el importe aplicado a la venta es S/35, el recibido es S/50 y el vuelto es S/15. Revise esos conceptos antes de confirmar; el total recibido no es ingreso neto adicional del negocio.

Si la red falla después de pulsar confirmar, consulte Pedidos y ventas antes de repetir. Una pantalla que perdió conexión no demuestra que la operación se haya cancelado. El sistema protege reintentos de la misma operación, pero crear otra venta diferente puede duplicar el cobro comercial.

Reimprimir un documento no crea una venta nueva. Compruebe papel, tamaño y vista de impresión en el dispositivo del cliente. La compatibilidad de una impresora térmica o lector concreto debe validarse físicamente en la entrega.

## 14. Pagos digitales, mixtos y crédito

### Efectivo y medios digitales

Los pagos Yape/BCP de esta entrega son manuales. Configure las instrucciones reales del negocio antes de ofrecer el medio. Un comprobante subido o una captura enviada no confirman el ingreso bancario. El propietario debe comprobarlo directamente en su propia cuenta y registrar una referencia válida.

En un pago mixto, revise cuánto corresponde a efectivo y cuánto al componente digital. El propietario verifica manualmente el digital; el rol Caja no puede autoaprobarlo. Si la verificación no está disponible, no registre el cobro como confirmado para cerrar la atención artificialmente.

No reutilice una referencia de pago para dos operaciones. Si la confirmación tarda, consulte la venta existente antes de intentar otro registro. El sistema no conecta una cuenta bancaria, no ejecuta un cargo y no devuelve dinero por sí solo.

### Venta al crédito

Antes de vender al crédito, registre o seleccione un cliente identificado. El propietario autoriza el límite y el plazo en **Clientes**, mediante **Autorizar límite de crédito**. Revise la deuda actual y el disponible antes de confirmar.

Una venta puede tener una parte pagada y otra pendiente cuando los controles de crédito lo permiten. Ejemplo independiente: total S/150, pago real S/50 y saldo por cobrar S/100. El saldo pendiente no es efectivo de caja.

Registre cada abono en el pedido al que pertenece, con su método y referencia. Compruebe el saldo después de confirmarlo. No cree otra venta para registrar un abono y no anote un ingreso manual duplicado de caja por el mismo dinero ya registrado como cobro.

Si el cliente paga digitalmente un saldo, primero verifique el ingreso real. La devolución de un artículo puede reducir deuda o generar un importe a devolver según la situación del pedido; revise el detalle antes de ejecutar un reembolso.

## 15. Clientes y pedidos de la tienda pública

En **Clientes**, utilice **Nuevo cliente** para registrar los datos necesarios de identificación y contacto. Revise duplicados y mantenga la información actualizada. Un consentimiento comercial, cuando se solicita, debe corresponder a la decisión real de la persona.

Un visitante puede explorar el catálogo sin iniciar sesión. Para confirmar una compra debe acceder o crear su perfil de comprador en esa tienda. Este perfil no concede acceso al inventario, a la caja ni a la administración del negocio.

El recorrido público pasa por carrito, datos y entrega, y pago. El comprador revisa cantidades, método de entrega, zona o punto, franja de atención y cupón si corresponde. Debe confirmar el total calculado por el sistema antes de registrar el pedido.

El pedido reserva las unidades durante 30 minutos mientras permanece pendiente según el flujo aplicable. El servicio programado libera reservas vencidas. Cerrar la pestaña no es una cancelación explícita y detener el programador impide procesar vencimientos a tiempo.

Cuando un medio de pago exige constancia, el archivo queda privado para su revisión. El propietario acepta o rechaza después de comprobar el ingreso real. Un pedido reservado, una constancia adjunta y una venta cobrada son estados distintos.

Los compradores pueden consultar **Mis pedidos** y sus notificaciones cuando su cuenta está correctamente asociada. La verificación de correo y la recuperación remota dependen de un servicio de correo real. En esta instalación el correo se registra localmente; antes de ofrecer el recorrido autónomo completo a compradores remotos, conecte y pruebe SMTP. No desactive la verificación para simular que ese servicio está operativo.

El negocio debe acordar qué datos necesita conservar y quién atiende las solicitudes del cliente. No exporte listas ni constancias a equipos compartidos sin una finalidad autorizada. El programa no sustituye la política de atención y privacidad del negocio.

## 16. Gestionar pedidos, devoluciones y reembolsos

En **Pedidos y ventas**, abra el pedido y compruebe número, cliente, artículos, importes, estado del pago y línea de tiempo antes de actuar. Los estados comerciales y logísticos representan cosas diferentes: preparar o entregar un pedido no prueba que esté pagado.

Actualice preparación, disponibilidad para recojo y entrega de acuerdo con lo que realmente ocurrió. Si debe cancelar una reserva pendiente, use su acción de cancelación; liberar la reserva no supone devolver stock ya vendido por segunda vez.

### Devolución de mercadería

1. Localice la venta original y su línea de producto.
2. Verifique la cantidad que todavía puede devolverse.
3. Registre la devolución y explique el motivo.
4. Indique la condición de lo recibido: disponible o dañado, según la pantalla.
5. Compruebe el efecto en existencias y saldo del pedido.

La mercadería dañada queda bloqueada para que no vuelva a venderse por error. La operación conserva el vínculo con la venta y su costo. Las ventas confirmadas se corrigen mediante movimientos y eventos, no eliminándolas del historial.

### Reembolso de dinero

El reembolso es una acción separada de la devolución física. El propietario revisa el importe que efectivamente corresponde devolver, ejecuta el pago real y registra método y referencia. Si sale efectivo, use una caja propia abierta y autorizada. Las acciones sensibles solicitan contraseña reciente.

No registre un reembolso que aún no se haya realizado ni devuelva más de lo que corresponde por cobros y ajustes del pedido. Cuando había crédito, compruebe primero el saldo pendiente y el efecto de la devolución; devolver un artículo no siempre significa entregar su precio íntegro en efectivo.

Después de corregir, revise la línea de tiempo, stock, deuda y caja. Conserve el motivo y la documentación que permita explicar el caso al cliente y al responsable del negocio.

## 17. Movimientos de caja y cierre diario

Durante el turno use **Ingreso / retiro de caja** para movimientos de efectivo que no sean cobros ya registrados. Indique importe y motivo concreto. El propietario puede registrar gastos con su categoría, método y referencia mediante la función correspondiente.

Evite duplicar una misma salida como gasto y como retiro sin comprender su efecto. Revise el movimiento generado por la operación original. Los pagos digitales y las ventas a crédito no incrementan automáticamente el efectivo físico contado.

Al finalizar:

1. Termine las operaciones en curso y revise ventas pendientes.
2. Sincronice las ventas sin conexión del turno y concilie sus dispositivos cuando corresponda.
3. Cuente físicamente el dinero de su caja, separado de otras cajas o fondos.
4. Abra **Cerrar y arquear** e introduzca el conteo real.
5. Revise la diferencia y anote su explicación.
6. Confirme el cierre y conserve el resultado para revisión del propietario.

No cambie el conteo para que coincida artificialmente con el saldo esperado. Una diferencia debe investigarse: fondo inicial incorrecto, vuelto, operación pendiente, gasto, retiro o registro en otra caja.

El propietario dispone de **Reabrir caja cerrada** cuando una corrección lo justifica. Exige contraseña reciente, motivo y condiciones válidas, incluida la ausencia de otra caja abierta para ese operador. La reapertura no elimina el arqueo anterior ni su auditoría.

Cada empleado utiliza su sesión y su caja. No comparta cuentas para trabajar dos turnos simultáneos. Al dejar el equipo, cierre la sesión de usuario; al cerrar el negocio, detenga los servicios solo cuando ya no queden operaciones locales pendientes.

## 18. Ofertas y cupones

El propietario administra **Promociones**. Una oferta automática puede definirse por porcentaje, producto o categoría, canal y período de vigencia. Use **Programar oferta automática**, revise fecha y hora y active únicamente la promoción aprobada por el negocio.

Los cupones permiten un código con descuento fijo o porcentual, ámbito aplicable, fechas, mínimo y límites de uso. Revise si corresponde a tienda pública, POS u otro canal que ofrezca la pantalla. Un cupón no debe prometer condiciones distintas de las que se configuraron.

Antes de anunciar una campaña:

1. Confirme productos y variantes incluidos.
2. Revise el precio base y el descuento esperado.
3. Compruebe fechas y horas con la configuración de Lima.
4. Pruebe una cotización elegible y otra que no cumpla las condiciones.
5. Compruebe mínimo de compra y límites globales o por cliente.
6. Revise el resultado en teléfono y escritorio si aparece en la portada.

El sistema selecciona la oferta aplicable según sus reglas y no suma indiscriminadamente ofertas y cupones. Se aplica un cupón por operación en el flujo previsto. Si cambia un descuento fijo por porcentual, revise el formulario completo antes de guardar.

Las reservas pueden consumir el uso del cupón y liberarlo cuando se cancelan o vencen según el flujo. No interprete el contador únicamente como ventas pagadas. Si el resultado difiere de lo esperado, revise el ámbito de productos elegibles, el canal, las fechas y las condiciones del cliente.

Modificar una promoción no debe utilizarse para alterar una venta histórica. El pedido conserva el cálculo aceptado para la operación. Desactive una campaña terminada y compruebe una cotización nueva para confirmar su estado.

## 19. Zonas, recojo y franjas de entrega

Primero habilite en el diseñador los métodos que el negocio atenderá. Después, en **Mi tienda**, utilice **Crear zona o punto de recojo** para registrar la opción, nombre, dirección o referencia y tarifa que correspondan al método.

Con **Crear franja de atención**, defina el intervalo y la capacidad disponible. Verifique que los horarios coincidan con los que puede atender el personal. Una capacidad publicada debe representar un compromiso operativo real.

Pruebe el recorrido como comprador: seleccione el método, zona o punto y franja; compruebe que el total incluye la tarifa esperada. Cuando hay zonas y franjas activas, el sistema valida la selección y su capacidad al confirmar. Tener una opción visible en una pestaña antigua no garantiza que todavía tenga cupo.

Ejemplo: si ofrece recojo en tienda y delivery por zona, diferencie la dirección de recojo de la dirección del comprador. No publique una tarifa genérica que luego sustituirá sin avisar. Los pedidos guardan los datos aplicables para que un cambio futuro de configuración no reescriba su historia.

Si deja de atender una zona o un horario, desactívelo para nuevas compras y revise los pedidos ya comprometidos. La desactivación no significa que los clientes anteriores hayan sido avisados automáticamente. Coordine su atención por el canal acordado.

Las ventas presenciales de POS y las ventas offline no necesitan seleccionar las franjas del checkout público. Si una venta requiere reparto, documente y gestione el compromiso de entrega en el flujo apropiado.

El enlace de WhatsApp facilita la conversación; no sustituye la aceptación del pedido, su cobro ni la actualización de su estado logístico.

## 20. Caja sin conexión: preparar y vender

Esta función permite continuar ciertas ventas en efectivo con existencias asignadas de antemano. Necesita preparación mientras el sistema está accesible; abrir por primera vez una página sin conexión no descarga el programa ni crea permisos.

### Preparación con conexión

1. Abra su caja personal en un almacén permitido.
2. Entre en **Caja sin conexión** y **Preparar caja sin conexión**.
3. Cree una **Nueva asignación**, identifique el equipo y seleccione productos y cantidades.
4. Descargue el archivo JSON de asignación y guárdelo de forma privada.
5. Abra **Abrir POS sin conexión** mientras tiene conexión y permita que termine la preparación del navegador.
6. Importe la asignación y establezca una **Clave local** de al menos ocho caracteres.

La asignación tiene una vigencia de 12 horas y reserva unidades exclusivas que dejan de estar disponibles para otras ventas. Trabaja con los datos y precios de esa instantánea. No cree varias asignaciones para el mismo equipo sin revisar las anteriores.

### Durante la desconexión

Venda únicamente los productos y cantidades autorizados, dentro del plazo. Este flujo admite ventas completas en efectivo; no ofrece crédito ni confirmación bancaria automática. Revise total, recibido y vuelto antes de confirmar.

Las operaciones quedan en una cola cifrada del navegador hasta sincronizarse. Después de una recarga puede desbloquearla con la misma clave local. No borre el almacenamiento, desinstale el navegador, cambie de perfil ni pierda la clave si existen pendientes.

Si aparece un error de almacenamiento o no se confirma el guardado local, detenga nuevas ventas, preserve la pestaña y solicite revisión. Una impresión o un mensaje verbal no demuestra que la cola haya guardado la operación. La durabilidad debe probarse en cada equipo y navegador que se utilice.

## 21. Sincronizar y conciliar dispositivos offline

Cuando el sistema vuelva a estar disponible, entre con el mismo operador y use **Sincronizar ventas guardadas**. Espere la respuesta, revise el contador de pendientes y compruebe las ventas en el servidor. Los reintentos de una operación reconocida conservan su identidad para evitar duplicarla.

No marque un dispositivo como terminado solo porque recuperó Internet. Puede faltar sincronización, haber un error de permisos o quedar ventas locales. Si la asignación venció, fue revocada o presenta una secuencia distinta, el propietario debe revisar las operaciones; no se deben liberar automáticamente existencias que podrían estar vendidas en el equipo.

Antes de cambiar de computadora o limpiar el navegador, genere el **respaldo cifrado** de los pendientes si la pantalla lo permite y conserve su clave. El archivo de asignación inicial no reemplaza el respaldo de ventas ya registradas. Tampoco el respaldo del servidor contiene ventas que nunca llegaron a PostgreSQL.

Para conciliar:

1. Identifique equipo, operador y asignación correctos.
2. Revise la última secuencia y la cantidad de ventas locales.
3. Sincronice o recupere todos los pendientes.
4. Compare los documentos aceptados por el servidor con el dispositivo.
5. Confirme la conciliación utilizando la secuencia final indicada, incluso cero cuando realmente no hubo ventas.
6. Compruebe la liberación de las unidades sobrantes y el efecto en caja.

No fuerce una conciliación vacía para recuperar stock sin revisar el equipo. Si se perdió el dispositivo, documente el incidente y sus ventas conocidas antes de decidir un ajuste.

En la entrega, pruebe desconexión, recarga, desbloqueo y sincronización con datos ficticios. También debe evaluarse el navegador real, reinicios, almacenamiento disponible y procedimiento del operador. Las pruebas de desarrollo no cubren todas las fallas físicas posibles.

## 22. Resumen, actividad y revisión de resultados

El propietario dispone de **Resumen** para revisar la operación. Seleccione el período que desea analizar y compruebe fechas, almacenes y conceptos antes de comparar resultados. Una cifra aislada no explica por sí sola el estado del negocio.

Distinga ventas, importes cobrados, deuda pendiente, devoluciones, reembolsos, gastos y efectivo de caja. Una venta a crédito puede existir sin haberse cobrado totalmente. Un retiro de caja no equivale a una reducción de ventas. Una devolución puede afectar mercadería y saldo antes de ejecutarse el reembolso.

Los costos y márgenes dependen de entradas con costo correcto. Si hay costos desconocidos o incompletos, interprete el resultado como parcial; no lo presente como utilidad contable definitiva. Cambiar el costo de una compra nueva no debe reescribir automáticamente el margen de ventas históricas.

En **Actividad**, consulte las acciones registradas para explicar cambios de permisos, operaciones y correcciones. La auditoría conserva trazabilidad; no es un formulario para borrar o editar movimientos anteriores. Si una operación no se entiende, use su identificador y momento para pedir soporte.

En una revisión diaria compruebe pedidos pendientes, reservas vencidas, pagos por verificar, diferencias de caja, productos con stock mínimo y dispositivos offline abiertos. Semanalmente revise deudas, compras no recibidas, permisos del personal y capacidad de almacenamiento.

Las exportaciones son herramientas de consulta, con límites de filas según la función. No equivalen a un respaldo completo porque no incluyen configuración, archivos privados ni todas las relaciones. Para recuperación utilice el paquete cifrado descrito en el capítulo siguiente.

Esta entrega no sustituye la contabilidad tributaria, la emisión fiscal ni una revisión profesional de obligaciones del negocio.

## 23. Crear, programar y revisar respaldos

Ejecute los comandos desde la carpeta de la instalación. Prepare una unidad o destino separado y confirme su letra real; `E:` es solo un ejemplo. Haga la copia sin operaciones en curso cuando sea posible para mejorar la coherencia entre base y archivos.

```powershell
.\respaldo-cliente.ps1 -Modo Nativo
.\respaldo-cliente.ps1 -Modo Nativo -CopiaSecundaria 'E:\RespaldosNegocio'
```

El paquete `.ssbk` incluye base, configuración y archivos privados, con cifrado autenticado y un manifiesto de comprobación. La clave `backup.key` queda fuera del paquete, en `claves/` o en la ubicación privada existente de `.runtime/`. El respaldo utiliza el rol de base correspondiente; la web continúa con su usuario restringido.

Guarde una copia protegida de la clave fuera del equipo y separada de los respaldos. Perderla impide descifrar los paquetes. La configuración cifrada incluye APP_KEY, necesaria para recuperar datos cifrados de la aplicación. No regenere esa clave para resolver problemas de acceso.

Después de cada ejecución abra **ESTADO_ULTIMO_RESPALDO.txt**. Contiene fecha UTC, resultado, archivo, destino secundario y detalle. `EXITOSO` confirma esa ejecución. Si falla la segunda copia, el resultado es `ERROR` aunque se conserve la copia principal. Investigue el motivo y repita cuando el destino esté disponible.

Para programar una copia diaria a las 22:00, según la hora de Windows:

```powershell
.\programar-respaldo.ps1 -Modo Nativo -Hora '22:00' -CopiaSecundaria 'E:\RespaldosNegocio'
```

El equipo debe estar encendido, el usuario conectado y la unidad accesible. Revise el resultado real de la tarea. Una carpeta adicional en el mismo disco no protege frente a pérdida de ese disco.

No hay eliminación automática de respaldos antiguos. El responsable debe revisar espacio, conservar versiones suficientes y acordar retención. Para retirar únicamente la tarea de esta instalación:

```powershell
.\programar-respaldo.ps1 -Eliminar
```

Este comando no elimina los archivos. El respaldo del servidor no contiene ventas offline sin sincronizar. La entrega no proporciona recuperación a cualquier segundo del pasado ni un tiempo de recuperación garantizado.

## 24. Restaurar y comprobar la recuperación

La recuperación debe realizarla el técnico autorizado con la clave original. Prepare el entorno destino y conserve intacta la instalación activa. La herramienta crea una base nueva; no necesita borrar la actual.

```powershell
.\restaurar-cliente.ps1 -Modo Nativo -ArchivoSsbk 'E:\RespaldosNegocio\archivo.ssbk' -KeyPath 'E:\Claves\backup.key' -Puerto 8010
```

Sustituya las rutas por las reales y lea la confirmación que solicita la herramienta. Antes de crear la base comprueba autenticidad, manifiesto, archivos y rutas. La base nueva se llama `sas_san_restore_<identificador>`; los archivos y la configuración se guardan en `.runtime/recuperaciones/<identificador>/`.

Si falla, no anuncie recuperación exitosa ni redirija el trabajo a una base parcial. El estado anterior permanece disponible y la nueva base puede conservarse para diagnóstico. Una clave incorrecta o un paquete alterado debe investigarse, sin omitir las validaciones.

Para revisar el resultado utilice la ruta de configuración mostrada por la herramienta:

```powershell
.\iniciar.ps1 -Configuracion 'RUTA_MOSTRADA\config.env'
```

La recuperación conserva APP_KEY del respaldo, usa las credenciales del runtime destino, dirige los archivos privados al espacio recuperado y deja el correo por registro local. Revise:

1. Acceso de propietario y segundo factor.
2. Datos de tienda, productos, variantes y fotos.
3. Existencias, reservas, bloqueos y movimientos.
4. Compras, ventas, deudas, devoluciones y reembolsos.
5. Saldos y cierres de caja.
6. Equipo, permisos y acceso restringido.

Compare con una referencia conocida y documente fecha, paquete, resultado y responsable. El técnico decide qué configuración se utilizará después de la aceptación; no copie a ciegas un archivo de otra empresa sobre la instalación activa. Los siguientes respaldos y su tarea deben recibir la misma configuración aceptada mediante `-Configuracion`.

Los respaldos antiguos sin manifiesto v2 requieren las herramientas documentadas para su versión. Ensaye la restauración antes de una emergencia; tener un archivo no demuestra que se sabe recuperar.

## 25. Inicio, apagado y actualización

Para comenzar el día, desde la carpeta de la copia:

```powershell
.\iniciar.ps1
```

El inicio utiliza la configuración de esa instalación, arranca los servicios y aplica migraciones incrementales cuando correspondan. Conserve su base, claves y archivos privados. Si el equipo se reinició, vuelva a iniciar el programa y compruebe las últimas operaciones antes de atender.

Para detenerlo, termine operaciones, sincronice pendientes y cierre cajas según el procedimiento. Después:

```powershell
.\detener.ps1
```

Si además desea detener PostgreSQL de esa instalación:

```powershell
.\detener.ps1 -DetenerBase
```

Estos comandos no eliminan ventas. No use el apagado como sustituto de confirmar formularios. Un pedido en pantalla sin confirmación no equivale a un registro guardado.

### Actualizar con soporte técnico

1. Identifique la versión instalada y la actualización que corresponde a esa copia.
2. Genere un respaldo y compruebe su destino y clave.
3. Termine y concilie las operaciones, incluidas las offline.
4. Detenga los servicios de la copia.
5. Aplique el código y recursos de la versión manteniendo configuración, base, claves y archivos privados.
6. Inicie y verifique migraciones, acceso, catálogo, stock y una revisión operativa.
7. Registre el resultado y conserve el respaldo previo.

No extraiga una instalación ya usada por otro cliente sobre la suya. No elimine `.runtime`, no regenere APP_KEY y no ejecute comandos de reinicio de base para actualizar. Las migraciones técnicas usan `sas_owner`; la aplicación sigue con `sas_runtime` sin privilegios de superusuario ni BYPASSRLS.

Si la actualización falla, el técnico debe decidir la recuperación considerando los cambios de esquema. Reemplazar archivos antiguos no garantiza deshacer una migración. La publicación en GitHub se realiza cuando se autoriza; no modifica por sí sola una instalación de cliente.

## 26. Solución de problemas frecuentes

| Situación | Comprobación y acción |
| --- | --- |
| Me envía a Perfil y no veo el negocio | Complete Seguridad y el segundo factor; después use Entrar a mi negocio. Ver mi tienda abre el catálogo público. |
| Mi cuenta no muestra Catálogo o Caja | Revise la membresía y el rol con el propietario. Un comprador no es empleado. |
| No puedo usar un almacén | Compruebe el alcance de almacenes, caja y dispositivo asignado. |
| Producto creado pero no se vende | Revise publicación, archivo y stock disponible en el almacén correcto. |
| Una foto no se carga | Use JPEG/PNG/WEBP, hasta 3 MB, 4000 px y 10 megapíxeles. |
| CSV rechazado | Revise encabezados, UTF-8, SKU, decimal con punto, tamaño y filas. Repita vista previa si venció. |
| No puedo cobrar | Revise caja personal abierta, existencias, permisos y crédito autorizado. |
| No llega una invitación o recuperación | El correo local queda en registro privado; el envío real necesita SMTP configurado y probado. |
| No acepta el código de seguridad | Revise cuenta, hora automática del teléfono y código vigente; utilice recuperación propia si corresponde. |
| El conteo físico fue rechazado | Actualice el corte: pudieron registrarse movimientos desde que abrió la pantalla. |
| Pago digital todavía pendiente | El propietario debe verificar el ingreso real y su referencia; una captura no basta. |
| Menos disponible que stock físico | Revise reservas, bloqueos, traslados y asignaciones offline. |
| Venta offline no sincroniza | Conserve datos y clave; revise operador, conexión, plazo y secuencia con el propietario. |
| Respaldo muestra ERROR | Lea detalle, permisos, espacio y segundo destino; no dé por hecha la copia externa. |
| Puerto ocupado | El técnico identifica el proceso y elige puertos de otra instalación nueva. No cierre proyectos ajenos a ciegas. |
| Página 503 por negocio | El técnico revisa selección/configuración de empresa; no desactive el aislamiento para entrar. |

Si la impresora falla, revise el documento existente y reimprima. Si una operación perdió conexión, búsquela antes de crear otra. Para soporte comunique pantalla, fecha, identificador de operación, mensaje exacto y pasos. No envíe contraseñas, códigos MFA, archivos de configuración, claves de respaldo ni registros completos.

## 27. Ejercicio de capacitación y aceptación

Realice este ejercicio en una copia con datos ficticios preparada para práctica. No lo ejecute como ventas simuladas dentro de la empresa real. El técnico debe identificar claramente la base aislada de pruebas.

1. Cree un producto de práctica con precio S/35 y una foto.
2. Reciba 10 unidades con costo unitario S/20. Compruebe 10 disponibles sin otras reservas.
3. Abra caja con S/100 de fondo inicial real para el ejercicio.
4. Venda una unidad en efectivo: aplique S/35, recibido S/50, vuelto S/15.
5. Compruebe existencia 9 y efectivo esperado S/135, sin otros movimientos.
6. Abra la nota interna y pruebe impresión o reimpresión sin generar otra venta.
7. Registre la devolución de esa unidad en condición disponible. Compruebe existencia 10.
8. Ejecute y registre el reembolso de S/35 desde la caja correspondiente. Compruebe efectivo esperado S/100.
9. Cierre y arquee la caja, verificando que el conteo coincide con el ejercicio.
10. Cree un respaldo y recupérelo en una base nueva para comparar los resultados.

Antes de entregar al negocio, complete además estas verificaciones con su responsable:

- Propietario puede entrar, configurar MFA y guardar recuperación en privado.
- Tienda tiene marca, contacto, horarios y tarifas aceptados.
- Personal ve únicamente las funciones y almacenes autorizados.
- Productos, fotos, variantes y cantidades iniciales corresponden a lo recibido.
- Pagos digitales se describen como manuales y solo se habilitan con instrucciones correctas.
- Reservas y servicios programados funcionan después de reiniciar.
- Offline, si se ofrece, se prueba en el equipo y navegador del cliente.
- Impresora y lector se prueban físicamente si forman parte de la operación.
- Respaldo externo, clave separada y restauración tienen responsables identificados.
- Quedan documentados los servicios no incluidos y las observaciones pendientes.

El acta debe registrar fecha, negocio, versión, equipo, responsable, resultado y pendientes. No se presume conformidad ni se firma por el cliente. La prueba de desarrollo no sustituye su aceptación operativa.

## 28. Entrega técnica, límites y referencia

### Para entregar otra copia independiente

El proveedor prepara una copia limpia con las herramientas de empaquetado del proyecto y sus binarios permitidos. Cada cliente recibe su propia base, credenciales SQL, APP_KEY, cookie y archivos privados. No duplique una carpeta ya instalada que contenga los datos de otro negocio. El nombre comercial por sí solo no aísla una base compartida.

La modalidad opcional de plataforma multiempresa tiene consola y permisos distintos. Solo se utiliza cuando se configura expresamente. Allí, una ayuda de catálogo requiere concesión explícita por tienda; no concede por sí sola caja ni propiedad. No debe confundirse con la copia independiente descrita en este manual.

### Para alojar posteriormente en Internet

El técnico debe preparar un servidor compatible con Laravel/PHP y PostgreSQL, dominio y DNS, HTTPS, secretos propios del entorno, correo probado, servicios de trabajos y programación supervisados, archivos privados protegidos y respaldo externo restaurable. Debe revisar cookies, errores públicos, hosts y permisos. No se usa el servidor de desarrollo de Laravel como servidor público definitivo.

No basta con subir la carpeta a cualquier hosting. PostgreSQL no debe exponerse públicamente. Correo, pagos y fiscalidad se conectan únicamente cuando el negocio autoriza y dispone de los servicios requeridos. Esta guía no afirma que exista ya ese despliegue público ni que se haya contratado infraestructura.

### Registro y documentación

Versión de referencia del código: `1187675ff3da80cf5aad6945e18fb06d29b7d065`. Este manual se apoya en las pantallas y procedimientos locales revisados al 31/10/2026. Las comprobaciones automatizadas documentadas no equivalen a certificación de seguridad, prueba de toda carga posible o validación de todos los periféricos.

Documentos técnicos incluidos en la carpeta `docs`: MANUAL_RAPIDO_OPERADOR.md, GUIA_RESPALDOS_Y_RECUPERACION.md, DESPLIEGUE_Y_ENTREGA.md, COPIAS_INDEPENDIENTES.md, SECURITY.md, PROGRESS.md y ACTA_ENTREGA_CONFORMIDAD.md. El historial puede contener estados anteriores; lea sus fechas y el alcance de la versión entregada.

Repositorio de referencia: https://github.com/josenunja/sistema-san-2.0

### Glosario operativo

| Término | Uso en el sistema |
| --- | --- |
| SKU | Identificador de un producto o variante con stock propio |
| Reserva | Unidades comprometidas antes de completar el flujo de venta |
| Venta | Operación comercial confirmada con sus artículos e importes |
| Cobro / abono | Dinero recibido y aplicado a una operación |
| Deuda | Parte que queda pendiente de cobrar al cliente |
| Arqueo | Comparación entre efectivo físico y saldo esperado |
| MFA | Segundo factor personal de autenticación |
| APP_KEY | Clave de cifrado de la aplicación que debe conservarse |
| backup.key | Clave separada que permite descifrar respaldos .ssbk |
| Conciliación offline | Revisión de ventas y unidades de un dispositivo antes de liberarlo |

### Ficha para completar al entregar

Negocio: ______________________________________________

Responsable propietario: _________________________________

Versión y fecha de entrega: _______________________________

Carpeta instalada y dirección local: ________________________

Responsable y destino del respaldo: ________________________

Fecha y resultado de restauración de prueba: ________________

Contacto de soporte y alcance acordado: ____________________

No anote contraseñas ni claves en esta ficha.
