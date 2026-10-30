# Revisión de funcionalidades y pendientes — 29 de octubre de 2026

## Alcance y criterio

Revisión del código local correspondiente a main / d0eb2ec, contrastado con README, PROJECT_CONTEXT, SECURITY, PROGRESS y la especificación original. Los documentos originales se tratan como requisitos de referencia, no como órdenes de implementar o activar integraciones en esta revisión. La petición más reciente de producto prevalece: una empresa por instalación, adaptable a ropa, panadería, mecánica u otro negocio.

Esta revisión identifica implementación, configuración y validación pendientes por separado. No incluye publicación, cambios de cuentas reales ni activación de servicios. No se asigna un porcentaje de terminación: las funciones pendientes tienen tamaños y dependencias diferentes.

## Flujos principales ya implementados

- Instalación inicial, acceso con contraseña/MFA y roles por empresa.
- Una empresa por copia; bloqueo de otras rutas/empresas y preparación de copias sin datos ni secretos.
- Diseñador con logo/portada, diez temas, borrador, vista previa y publicación.
- Productos/variantes, precio en céntimos, fotos WEBP, publicación y CSV con validación previa. Carga rápida con nombre/precio, código automático y foto opcional.
- Inventario, recepciones, costos, reservas, traslados con recepción parcial/daños y conteos.
- Pedidos, pagos manuales, verificación de constancias, crédito/abonos, devoluciones y reembolsos.
- POS, apertura/cierre de caja, movimientos, gastos, informes y auditoría.
- Invitaciones con historial paginado, reenvío que invalida el enlace previo y revocación. El propietario y un usuario autorizado como catalog pueden cargar productos.
- Lógica de asignación/sincronización offline y scripts de respaldo cifrado/restauración aislada.

## Prioridad para entregar copias utilizables

| Prioridad | Pendiente comprobado | Consecuencia y cierre esperado | Evidencia en código |
|---|---|---|---|
| Alta | Ejecución persistente del scheduler en la instalación Docker | Compose solo levanta app y db. El inicio nativo sí inicia schedule:work; el camino Docker no lo hace. Aunque orders:expire está programado cada minuto en Laravel, necesita un proceso que ejecute el scheduler. Añadirlo y verificar una reserva vencida sin ejecutar el comando manualmente. Incorporar un worker si se empiezan a enviar tareas a la cola. | app/compose.yaml, iniciar.ps1, app/routes/console.php |
| Alta | Política de límites adaptada al modo independiente | La copia conserva el plan inicial: 500 productos, 2 almacenes y 5 usuarios. La pantalla de Plataforma que cambia el plan está bloqueada en modo single. Mostrar consumo/límites y definir una vía autorizada de configuración para esa instalación, conservando límites explícitos. No se propone eliminarlos silenciosamente. | migración 000003; CatalogController, CatalogToolsController, Invitations, BusinessController; middleware SingleBusiness |
| Alta | Instalación limpia de las copias en otro entorno | El generador produce código y configuración separados, pero falta arrancar y aceptar ropa/panadería/mecánica con sus bases y volúmenes independientes. Validar instalación, reinicio, actualización, respaldo/restauración y acceso cruzado denegado entre las instalaciones completas. Docker del equipo conservaba un incidente de disco; esta revisión no lo reparó ni reinicializó. | preparar-copia.ps1, docs/COPIAS_INDEPENDIENTES.md |
| Alta si se usarán invitaciones o recuperación remota | Correo real y prueba de entrega | La aplicación tiene soporte SMTP y las notificaciones, pero MAIL_MAILER=log en el entorno local. Las invitaciones, verificación y recuperación no llegan a un buzón. Configurar el proveedor elegido y probar entrega/reenvío/caducidad/recuperación, sin publicar los enlaces privados. | app/config/mail.php, BusinessInvitation, .env.example |

La base de trabajo sas_san sigue sin cuentas ni empresas: lectura de solo conteos del 29/10 confirmó 0 usuarios y 0 empresas. Falta que su propietario complete /instalacion y MFA. Es configuración inicial pendiente, no ausencia del módulo ni una contraseña que deba inventarse.

## Funcionalidad comercial todavía parcial

| Área | Existe | Falta implementar o ampliar |
|---|---|---|
| Caja/POS | Venta, efectivo inicial, crédito y abonos; pagos digitales manuales posteriores | Pago mixto atómico dentro de una misma venta, vuelto explícito y reapertura autorizada/auditada de caja. createOrder recibe cash_minor, no una colección de medios de pago. |
| Compras | Documento de un producto/variante con recepciones parciales y reversos | Documento con varias líneas, descuentos de compra y distribución de flete/otros costos adicionales con una regla explícita. Purchasing trabaja con un product_id por compra. |
| Personal/sucursales | owner, cashier, warehouse y catalog por empresa | Rol de encargado configurable y permisos por sucursal/almacén. Las membresías actuales son tenant_id/user_id/role; un cajero/almacenero no queda restringido a un almacén asignado. Panel de sesiones activas y revocación individual no encontrado en rutas/pantallas propias. |
| Catálogo/fotos | Variantes, varias fotos, publicación y CSV | Cambiar portada, ordenar/eliminar fotos y texto alternativo individual; estado de archivo separado de publicado; código de barras dedicado y atributos configurables. Las rutas actuales permiten cargar fotos, pero no completar su ciclo de gestión. |
| Promociones | Cupones con vigencia, importe o porcentaje, categoría/variante/canal y límites | Ofertas automáticas/programadas por variante sin introducir cupón, reglas de combinación/prioridad y descuentos manuales de cajero con límites. El precio anterior visual no programa una oferta. |
| Inventario/traslados | Despacho y recepción parcial, daños, kardex y conteo | Etapas separadas de solicitud/preparación, ubicaciones internas, stock mínimo/alertas y documento masivo de apertura. No confundirlos con las recepciones manuales ya existentes. |
| Entrega | Recojo/delivery/courier/envío nacional con tarifas por método y datos de contacto/horario | Tarifas por distrito/zona, disponibilidad por franja y selección de puntos de recojo. El horario publicado del negocio no valida cupos de entrega. |
| Informes/clientes | Ventas/cobros/deuda, costos, gastos y métricas básicas de clientes | Filtros por sucursal/almacén/cajero/producto, CRM avanzado y conservación/anonimización conforme a una política definida. Varios selectores se limitan a 500 productos/clientes y compras a 200 sin paginación completa; movimientos, a 100. Exportación actual, máximo 10.000 filas. |
| Interfaz | Gestión y carga rápida en español | Sustituir textos/enlaces del starter en configuración personal; mejorar búsqueda/paginación de operaciones y usar selecciones guiadas donde hoy se pide escribir un número de compra/recepción. |

La compra con cuenta es una decisión posterior explícita del usuario y está aplicada en checkout. La compra como invitado del documento original no se marca como error pendiente: requeriría cambiar esa decisión de producto.

## Integraciones y módulos sujetos a decisión

- Fiscal: solo contrato FiscalProvider, estado unconfigured y ticket interno. Falta un adaptador real, credenciales de pruebas, ciclo de emisión/consulta y evidencia del proveedor. El usuario indicó que todavía no tiene proveedor; no activar ni simular aceptación.
- Pagos automáticos: Yape/BCP son instrucciones y revisión manual. Faltan proveedor/pasarela, validación servidor-servidor, webhooks firmados y conciliación automática si se solicitan. La revisión manual implementada sí puede utilizarse dentro de su alcance.
- WhatsApp: existe enlace que el usuario decide enviar; no mensajes automáticos ni adjuntos gestionados por API. Esto no impide el catálogo ni la venta manual.
- Rubros especializados: elegir panadería o mecánica adapta la presentación del comercio. No crea recetas/producción, citas, vehículos u órdenes de reparación. No se consideran requisitos nuevos aceptados por el solo nombre del rubro. Cantidades enteras son una decisión del alcance; venta por peso no está pedida.
- SaaS: si se vuelve al modo platform, faltan estados trial/vencido/gracia, facturación de suscripciones, cuotas de almacenamiento/módulos y soporte delegado temporal. No son necesarios para el objetivo actual de una empresa por copia.

## Infraestructura y validación por completar

1. Despliegue productivo reproducible: proxy/HTTPS, PHP-FPM y procesos sin root, debug apagado, hosts/proxies/cookies verificados, secretos independientes por rol y entorno. El Dockerfile actual termina en artisan serve y Compose monta el código fuente; corresponde a desarrollo.
2. Recuperación: automatizar retención y copia cifrada fuera del equipo; recuperar con volumen representativo y medir tiempos. WAL/PITR y objetivos de recuperación siguen pendientes. El respaldo/restauración local aislado ya existe.
3. Observabilidad: alertas operativas, métricas/latencias y destino independiente de auditoría. Existe auditoría de negocio y /up; esto no demuestra readiness de base/colas ni supervisión productiva.
4. Aceptación de offline en navegadores/dispositivos: desconexión real, reinicio, almacenamiento lleno, varias pestañas, sesión vencida, revocación y recuperación de pendientes. La suite de dominio no sustituye esas pruebas.
5. CI: completar E2E de navegador, análisis de imagen/configuración, detección automatizada de secretos y pruebas de carga p50/p95. El workflow sí contiene análisis estático, build, pruebas PostgreSQL con sas_runtime, carreras y auditorías de dependencias. Las frases antiguas que dicen que nunca se ejecutó CI quedaron desactualizadas frente a la publicación del 25/10.
6. Revisar las seis propuestas Dependabot abiertas observadas el 29/10 antes de incorporarlas. No son seis funcionalidades faltantes ni equivalen por sí solas a seis vulnerabilidades.
7. Cierre de identidad: revisar Argon2id con parámetros medidos frente al requisito original; el valor por defecto del framework es bcrypt y no se encontró HASH_DRIVER en las plantillas/configuración local revisadas. Esta diferencia de requisito no demuestra por sí sola una vulnerabilidad explotable.

## Comprobaciones de esta revisión

- Lectura de requisitos, rutas, controladores, servicios, migraciones, pantallas, scripts y configuración CI/Compose; no solo búsqueda de TODO.
- La comparación anterior del mismo día verificó los 421 archivos versionados locales idénticos a main / d0eb2ec y actualizó las referencias remotas.
- Base de trabajo consultada sin modificar registros: 0 usuarios / 0 empresas; sas_runtime con rolsuper=false y rolbypassrls=false.
- PostgreSQL portátil estaba detenido tras la interrupción del equipo. El primer arranque dentro del entorno restringido no persistió; se inició de nuevo con acceso nativo y se comprobó que aceptaba conexiones. Un intento de pruebas durante esa recuperación dio errores de disponibilidad y no se cuenta como aprobación.
- npm run types:check: aprobado.
- Repetición de verificar.ps1 con PostgreSQL disponible: 73 pruebas aprobadas / 766 aserciones y cinco carreras independientes aprobadas (última unidad, cupón, cobro, devolución y replay offline). Base sas_san_test, runtime sas_runtime. Este filtro de negocio no ejecuta toda la batería heredada de autenticación/configuración del starter.
- npm run check: aprobado; 93 archivos revisados por formato y 79 por lint, sin avisos ni errores.
- No se repitieron build, PHPStan, restauración, aceptación visual ni instalación Docker en esta revisión. No hubo cambios de código que requirieran recompilar; las evidencias del 25/10 conservan su fecha y alcance.

## Orden propuesto

Cerrar primero instalación/scheduler y límites del modo independiente; después correo y aceptación de una copia nueva. Continuar con pago mixto y compras multilínea, permisos por almacén y gestión completa del catálogo. Preparar infraestructura, respaldo externo y aceptación offline antes de ofrecer el sistema como servicio público. Facturación/pagos automáticos se implementan cuando se elijan proveedores y se autoricen sus conexiones.
