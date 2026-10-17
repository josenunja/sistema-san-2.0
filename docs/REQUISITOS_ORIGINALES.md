# Prompts completos: plataforma SaaS multiempresa de comercio y gestión

Preparado para José Guadalupe Nunja Saucedo. Fecha: 17 de octubre de 2026.

## Cómo utilizar esta secuencia

El editor del ícono azul es **Visual Studio Code (VS Code)**. Abre una carpeta nueva llamada `plataforma-multinegocio` y utiliza su asistente de programación en modo agente, con acceso a los archivos y la terminal.

Pega primero el **prompt maestro** y después los **24 prompts numerados**, uno por uno. Cada etapa debe modificar el proyecto, ejecutarse y verificarse antes de avanzar. No pegues toda la secuencia en una única ejecución. Si cambias de conversación, pide leer `AGENTS.md`, `docs/PROJECT_CONTEXT.md` y `docs/PROGRESS.md` antes de continuar.

Este documento contiene instrucciones de desarrollo; no representa una aplicación implementada, una auditoría realizada ni una conexión activa a servicios externos. El trabajo comienza localmente y prepara el despliegue. No se publicará automáticamente ni se crearán instaladores de escritorio.

### Decisiones de arquitectura

| Capa | Decisión |
| --- | --- |
| Editor | VS Code; en Windows, preferir WSL2 y Docker Compose si están disponibles. Diagnosticar recursos y herramientas antes de instalar. |
| Backend | PHP 8.4, Laravel 13, monolito modular y autenticación nativa del framework. |
| Interfaz | Vue 3, TypeScript estricto, Inertia y Tailwind mediante el starter oficial compatible. |
| Persistencia | PostgreSQL 18; esquema compartido con tenant_id, restricciones relacionales y Row Level Security. |
| Procesos | Colas, caché y límites de frecuencia sobre Redis; verificar licencia y fijar versión compatible al crear el entorno. |
| Archivos | Adaptador de almacenamiento local privado en desarrollo y compatible con S3 para producción. Fotos publicadas separadas de documentos privados. |
| Operación sin internet | Módulo PWA exclusivo para punto de venta, con IndexedDB, permisos limitados y sincronización idempotente. |
| Infraestructura | Docker Compose para desarrollo y despliegue inicial documentado; proxy web, aplicación, worker, scheduler y servicios internos separados. |
| Seguridad | Controles verificables basados en OWASP ASVS nivel 2, aislamiento entre empresas y mínimo privilegio. No afirmar certificación ni seguridad absoluta. |

La versión exacta de cada dependencia y de las imágenes se resolverá con documentación oficial, compatibilidad y parches disponibles al ejecutar el prompt 01. Se conservarán los lockfiles; no se utilizarán etiquetas flotantes `latest` en producción. No añadir microservicios, Kubernetes ni una API independiente para toda la interfaz sin una necesidad demostrada.

### Alcance confirmado

Varias empresas de distintos rubros, cada una con tienda pública y administración independiente; sucursales y almacenes; productos y variantes por unidades enteras; carga masiva; promociones; carrito; compra como invitado o con cuenta; entrega y recojo; Yape y BCP configurables por empresa; constancias sujetas a verificación; pedidos guardados antes de abrir WhatsApp del negocio; ventas presenciales y caja; crédito y abonos; tickets y preparación de comprobantes electrónicos; clientes/CRM; inventario físico, reservas, bloqueos y tránsito; reportes y utilidad basada en costos; POS sin conexión y sincronización posterior. El superadministrador administra la plataforma y sus planes.

Una tienda no mezclará productos de otras empresas en su carrito. El registro de una venta no equivaldrá a pago recibido ni a comprobante electrónico aceptado. Los pedidos, pagos, entregas y comprobantes tendrán estados independientes.

## Prompt maestro — contrato permanente del proyecto

```text
Actúa como ingeniero principal de software, especialista en Laravel, PostgreSQL, comercio electrónico, inventarios y seguridad de plataformas multiempresa. Implementa cambios reales en archivos y verifica su funcionamiento; no te limites a explicar ni a generar pantallas con datos ficticios.

Construimos una plataforma SaaS multiempresa y multirrubro llamada provisionalmente plataforma-multinegocio. Cada empresa tiene tienda pública, administración, productos, variantes, clientes, promociones, carrito, pedidos, pagos, sucursales, almacenes, inventario y reportes independientes. Los productos se manejan por unidades enteras. El administrador edita su tienda; el cliente navega públicamente y compra como invitado o con cuenta. Hay POS, caja, crédito y abonos. Cada empresa configura su WhatsApp, Yape y BCP. El pedido se guarda antes de abrir WhatsApp. Incluye tickets y una integración fiscal separada, POS sin conexión y sincronización segura. El superadministrador gestiona empresas y planes, con acceso empresarial sensible restringido y auditado.

Base técnica: PHP 8.4 y Laravel 13; Vue 3, TypeScript estricto, Inertia y Tailwind del starter oficial compatible; PostgreSQL 18; Redis; Docker Compose; almacenamiento desacoplado; monolito modular. Verifica versiones oficiales, compatibilidad y herramientas disponibles. Si el equipo requiere otro parche o una alternativa de entorno, documenta la decisión sin cambiar arbitrariamente la arquitectura. Utiliza autenticación nativa; no obligues a contratar un proveedor de identidad. La interfaz será en español, moneda inicial PEN y zona horaria empresarial inicial America/Lima; almacena instantes en UTC y aplica la zona de cada empresa a reportes y promociones.

Reglas permanentes:
1. Lee las instrucciones existentes, examina el estado y conserva el trabajo ajeno. No reinicies el proyecto, borres bases ni ejecutes migraciones destructivas sobre datos existentes. Toda prueba destructiva debe quedar confinada a una base de pruebas identificada.
2. Mantén controladores delgados, validación de entrada, autorización mediante Policies/Gates, servicios de aplicación y transacciones para operaciones críticas. No impongas repositorios genéricos ni abstracciones innecesarias.
3. Resuelve la empresa desde una ruta/dominio validado y verifica pertenencia. Nunca confíes en tenant_id, rol, precio, descuento, total o estado de pago enviados por el navegador. Aplica controles también a colas, caché, archivos, búsquedas, exportaciones y procesos programados.
4. Usa PostgreSQL real para pruebas de aislamiento, RLS, concurrencia y bloqueos. Usa roles separados para migración y operación; el runtime no tendrá SUPERUSER, BYPASSRLS ni propiedad de tablas. Las restricciones SQL complementan la autorización de aplicación.
5. Calcula dinero con enteros de unidades monetarias menores o decimales exactos; nunca floats binarios. Documenta el redondeo fiscal y comercial. Mantén snapshots de precio, descuento, costo y datos de venta para preservar el historial.
6. Las operaciones de venta, cobro, reserva, devolución y sincronización deben tolerar reintentos sin duplicar efectos. Implementa claves idempotentes y restricciones únicas, bloqueos ordenados y estados válidos. Publica trabajos externos después del commit mediante outbox cuando corresponda.
7. No inventes credenciales, números de cuenta, RUC, certificados, webhooks, APIs bancarias ni respuestas aceptadas por SUNAT. Separa adaptadores simulados de pruebas y reales. Lo no configurado permanece deshabilitado con estado claro. No envíes comunicaciones reales ni actives cobros reales durante el desarrollo.
8. Protege secretos fuera del repositorio y del frontend. Usa sesiones en cookies HttpOnly, protecciones CSRF, límites de frecuencia, validación de archivos, consultas parametrizadas, mínimo privilegio y MFA para administración. No guardes tokens administrativos en localStorage. No afirmes que UUID, cifrado o botones ocultos sustituyen autorización.
9. Conserva inventario y trazabilidad mediante movimientos y reversos, sin editar silenciosamente el pasado. El carrito no reserva por sí solo. Define claramente cuándo se reserva, consume, despacha, devuelve o libera cada unidad.
10. Desarrollo local primero. Prepara configuración y procedimientos de despliegue sin publicar, contratar recursos, generar instaladores ni activar integraciones externas. Staging y producción son entornos separados y requieren datos reales verificados.
11. Objetivo de seguridad: requisitos aplicables de OWASP ASVS nivel 2 con matriz de evidencia. No anuncies certificación ni seguridad absoluta. Corrige fallos demostrados; no alteres una prueba para ocultarlos.
12. Usa datos de demostración únicamente mediante una acción explícita en local/testing. Nunca crees cuentas universales ni contraseñas públicas en producción. No marques un módulo terminado por tener su menú o su maqueta.

Documentación permanente: AGENTS.md, README.md, docs/PROJECT_CONTEXT.md, docs/ARCHITECTURE.md, docs/SECURITY.md, docs/DATA_MODEL.md, docs/PROGRESS.md y docs/EXTERNAL_CONNECTIONS.md. Registra decisiones importantes en docs/adr/. Estos archivos deben permitir continuar en otra sesión.

Para cada prompt: revisa lo existente, implementa la etapa, ejecuta los controles pertinentes y corrige sus fallos. Entrega un resumen de cambios, comandos realmente ejecutados, resultados, limitaciones y siguiente etapa. Si no puedes ejecutar una comprobación, indica por qué y deja el comando reproducible. No inventes pruebas aprobadas ni conexiones realizadas. Actualiza el progreso con estados pendiente, implementado, probado o bloqueado y evidencia. Pide datos solo cuando sean imprescindibles; resuelve decisiones rutinarias con criterio técnico.
```

## Prompt 01 — entorno, repositorio y estructura modular

```text
Aplica el prompt maestro y prepara el proyecto local en la carpeta abierta. Detecta sistema operativo, shell, memoria disponible cuando sea observable, PHP, Composer, Node, Git, Docker y WSL. No supongas que conoces el equipo. Si falta una herramienta, explica el requisito concreto y aprovecha los pasos que sí puedan ejecutarse. No instales componentes globales ni cambies configuraciones del sistema sin necesidad.

Si la carpeta está vacía, inicializa Laravel 13 con el starter oficial Vue, TypeScript e Inertia compatible. Usa PHP 8.4, PostgreSQL 18 y Node LTS compatible; resuelve parches y dependencias con documentación oficial y fija lockfiles. Si existe una aplicación, audítala y adapta sin sobrescribirla. No uses SQLite como sustituto de la base definitiva.

Establece módulos Tenancy, Identity, Catalog, Storefront, Inventory, Purchasing, Pricing, Sales, Orders, Payments, Customers, Fiscal, Messaging, Reporting, Offline y Platform. Adopta estructura Laravel convencional con servicios por dominio, FormRequests, Policies, Jobs y DTOs cuando aporten claridad. Conserva el starter reconocible.

Prepara .env.example sin secretos, .gitignore, formato PHP, lint y tipos TypeScript, comandos de desarrollo, Docker Compose local con puertos publicados solo en loopback, correo de pruebas y almacenamiento privado. Documenta extensiones PHP y conexión desde VS Code; explica WSL2/Docker y una alternativa nativa si el entorno lo requiere. No expongas herramientas de administración de base de datos.

Crea los documentos permanentes, un mapa de los 24 pasos y un registro de dependencias. Verifica inicio de aplicación, conexión a PostgreSQL, compilación y una prueba básica significativa. Criterio: entorno reproducible desde README y ninguna credencial versionada.
```

## Prompt 02 — modelo relacional y reglas de negocio

```text
Lee el contexto y diseña el modelo de datos completo antes de ampliar funcionalidades. Documenta un diagrama Mermaid y un diccionario con tipos, claves, estados, índices, unicidad y retención. Implementa las migraciones fundacionales y distribuye las demás por sus módulos sin crear tablas sin uso innecesariamente.

Incluye empresas, configuración, identidad global mínima, membresías del personal por empresa, permisos, sucursales, almacenes, cajas, productos, variantes/SKU, categorías, atributos, imágenes, proveedores, recepciones, posiciones y movimientos de inventario, reservas, transferencias, promociones, clientes por empresa, direcciones, carritos, pedidos y líneas, ventas y líneas, pagos y aplicaciones, cuentas por cobrar, abonos, devoluciones, documentos fiscales, dispositivos offline, asignaciones de stock offline, outbox, auditoría y planes/suscripciones.

Los datos comerciales llevarán tenant_id no nulo. Usa claves o restricciones compuestas para impedir referencias cruzadas entre empresas, además de Policies. Define qué tablas son globales y por qué. La identidad global nunca permitirá compartir automáticamente direcciones, deudas o compras entre negocios. Define SKU único por empresa, y no necesariamente global; cantidades enteras y restricciones de no negatividad donde correspondan.

Documenta estados independientes de pedido, pago, entrega, documento fiscal y sincronización. Define una matriz de transiciones autorizadas e invariantes: total pagado correctamente aplicado, reserva que no cuenta como venta, transferencia que no crea unidades y devolución que no reintegra stock dos veces. Guarda snapshots históricos y fechas UTC.

Prueba las restricciones mediante PostgreSQL real, incluyendo una referencia entre empresas rechazada y dinero redondeado exactamente. Criterio: modelo explicable, migraciones reproducibles y relaciones incapaces de mezclar empresas por un error de formulario.
```

## Prompt 03 — autenticación, cuentas y permisos

```text
Implementa identidad y autorización usando los mecanismos mantenidos de Laravel y el starter oficial. Incluye login, logout, recuperación de contraseña, verificación de correo, invitación de trabajadores, caducidad de invitaciones, gestión de sesiones y revocación. Las invitaciones deben ser de un solo uso, asociadas a empresa/rol y no permitir autoasignarse privilegios.

Roles iniciales: propietario de plataforma, administrador de empresa, encargado, cajero, almacenero y cliente. Los permisos del personal se asignan por empresa y, cuando proceda, por sucursal/almacén. No uses un rol global admin para acceder a todos los negocios. El cliente tiene acceso a sus propios pedidos y perfil comercial dentro de cada tienda. No habilites registro público de administradores de empresa por defecto.

Protege con MFA TOTP las cuentas administrativas, confirma su activación y exige reautenticación para cambiar pagos, credenciales, permisos o factores. Añade recuperación segura de MFA sin un bypass universal. Hashea contraseñas con Argon2id compatible y parámetros medidos; no trunques contraseñas. Limita intentos sin permitir un bloqueo permanente fácil de explotar; evita revelar si una cuenta existe.

Usa cookies HttpOnly, SameSite y Secure según entorno; protege CSRF, rota sesión al autenticar y autorizar cambios relevantes. No almacenes secretos de sesión en localStorage. Separa pantallas públicas de las administrativas; el login redirige según rol y membresía validada, con selector si administra varias empresas.

Prueba escalamiento de rol, invitación reutilizada, acceso de cliente a administración, recuperación, revocación y comprobación de permisos desde peticiones directas. Criterio: ocultar un botón nunca será la única protección.
```

## Prompt 04 — aislamiento multiempresa y RLS

```text
Implementa el TenantContext y el aislamiento de defensa en profundidad. Empieza con /tienda/{slug} para la tienda, /gestion/{slug} para personal y /plataforma para control global. La ruta o dominio identifica la empresa; valida membresía, estado y autorización para cada acción privada. Un tenant_id aportado por el cliente no define el contexto.

Activa Row Level Security en tablas comerciales con políticas de lectura y escritura, USING y WITH CHECK, y comportamiento de rechazo sin contexto. Define un registro público mínimo de tiendas para resolver slug sin exponer configuración privada. La autenticación y las membresías requieren una ruta de bootstrap estrecha y documentada; no desactives RLS globalmente para resolverla.

Separa rol propietario/migrador y rol runtime. El runtime no será propietario, superusuario ni BYPASSRLS y no podrá cambiar roles. Aplica FORCE ROW LEVEL SECURITY donde corresponda. Configura el contexto con SET LOCAL parametrizado en transacciones acotadas y verifica su limpieza ante excepciones y conexiones reutilizadas. No uses una variable de sesión persistente que pueda contaminar otra petición. Revisa vistas, consultas raw y relaciones.

Extiende aislamiento a claves de caché, sesiones de tienda, archivos, descargas firmadas, jobs, reportes, índices y exportaciones. Los jobs establecen y limpian contexto explícitamente. El catálogo público usa consultas y DTOs con campos permitidos; no publica costos, correos privados ni datos del personal. El superadministrador no obtiene un bypass universal de datos comerciales.

Prueba con empresas A/B: URL manipulada, IDs ajenos, escrituras, descargas, consultas SQL mediante rol runtime, caché compartida y dos jobs consecutivos de empresas diferentes. Criterio: evidencia de ausencia de lectura o modificación cruzada, incluso tras errores y reutilización de conexiones.
```

## Prompt 05 — interfaz profesional y navegación por rol

```text
Construye el sistema visual en Vue/TypeScript y Tailwind sobre el starter real. Diseña tres áreas consistentes: tienda pública, gestión empresarial y plataforma. Todo en español, responsive, accesible por teclado, con foco visible, etiquetas, contraste suficiente y formatos locales de moneda/fecha. No uses pantallas basadas solo en emojis, tablas sin jerarquía ni datos estáticos presentados como reales.

La tienda tendrá encabezado de marca, búsqueda, categorías, promociones, productos, carrito y cuenta. Gestión tendrá navegación lateral por módulos, selector de sucursal/almacén, indicadores y acciones principales. Plataforma mostrará empresas y estado del servicio. El login de un administrador entra a su gestión; el cliente accede a su cuenta. Añade enlace Vista de mi tienda y retorno autorizado a gestión.

Crea componentes reutilizables de tablas paginadas en servidor, filtros, formularios, validación, confirmaciones de operaciones sensibles, estados de carga/vacío/error y avisos de permisos. Los formularios conservarán datos ante errores y no repetirán escrituras por doble clic. Evita solicitudes y renderizados innecesarios en catálogos extensos.

No incluyas secretos en props de Inertia ni en respuestas HTML. Escapa contenido, evita v-html con entradas no confiables y establece una política compatible con futura CSP. Usa variables de tema validadas y componentes tipados.

Verifica navegación en móvil y escritorio, teclado y roles. Criterio: layouts conectados a rutas y permisos reales; los módulos aún pendientes deben identificarse, sin inventar estadísticas. Guarda las decisiones visuales y componentes para que los próximos pasos no rediseñen todo.
```

## Prompt 06 — editor de tienda y configuración empresarial

```text
Implementa el editor de cada tienda: nombre, logo, colores, portada, textos, datos de contacto, horarios, categorías destacadas y secciones promocionales. Permite vista previa privada, guardado en borrador y publicación transaccional de una versión; restaurar una versión visual no debe revertir inventario, precios históricos ni pedidos.

Usa bloques predefinidos y campos validados. No permitas JavaScript, HTML arbitrario ni CSS libre suministrado por el comerciante. Valida enlaces y esquemas para evitar javascript: y redirecciones inseguras. Los cambios de slug deben conservar referencias y manejar colisiones de forma segura. Mantén un historial de quién publicó y cuándo.

Añade configuración por empresa de sucursales, almacenes, zona horaria, moneda inicial PEN, contacto WhatsApp y condiciones de entrega/recojo. Los datos de pago y las credenciales externas tendrán pantallas protegidas con reautenticación; no se guardan en la configuración pública del tema. Los cambios de cuentas de cobro generan auditoría y aviso interno.

El editor solo devuelve recursos de su empresa. Fotos públicas publicadas y documentos privados viven en espacios separados. Valida tamaño, dimensiones y tipo real de imágenes, elimina metadatos sensibles y evita SVG activo sin sanitización segura. El procesamiento asíncrono no debe perder el tenant.

Prueba borrador no visible al público, publicación, rechazo de scripts, modificación de una tienda ajena y caché invalidada solo para la tienda editada. Criterio: administrador puede cambiar su fachada sin tocar código ni exponer información de otras empresas.
```

## Prompt 07 — productos, variantes, imágenes e importación masiva

```text
Implementa catálogo administrativo con categorías, atributos configurables, producto, variantes/SKU, nombre, descripción, código de barras opcional, precio, costo de referencia y estado publicado/archivado. Cada producto simple tendrá una variante operativa para unificar ventas e inventario. Tallas y colores crean variantes con existencias propias. Cantidades enteras por unidad; no implementar venta por peso.

Permite varias fotografías, portada, orden y texto alternativo. Al archivar un producto conserva líneas históricas, movimientos y documentos. Los cambios de precio no alteran pedidos anteriores. Aplica validación en servidor, índices tenant-aware y búsqueda paginada. No expongas costos en el catálogo público.

Implementa importación mediante plantilla CSV UTF-8; si añades XLSX, justifica librería mantenida y sus límites. Flujo: subir, validar, previsualizar, mostrar errores por fila, elegir alcance y confirmar. Distingue crear/actualizar por SKU y no dupliques por reintento. La importación de productos no debe modificar inventario silenciosamente: inventario inicial se ejecuta como documento de apertura separado y autorizado.

Procesa archivos en área privada con límites de tamaño/filas, verificación de contenido, cuotas por empresa y jobs con contexto. No descargues imágenes desde URLs arbitrarias sin controles SSRF; preferir carga directa o paquete validado. Neutraliza fórmulas peligrosas en exportaciones CSV para hojas de cálculo.

Prueba variantes, duplicados, importación parcial documentada, reintento, filas inválidas, aislamiento y archivo malicioso. Criterio: catálogo editable y masivo con errores claros y sin desajustes de stock.
```

## Prompt 08 — inventario, almacenes, kardex y transferencias

```text
Implementa el núcleo de inventario con movimientos auditables e inmutables para entradas, salidas, apertura, ajustes, daños, bloqueos, reservas, despachos y devoluciones. Modela almacenes por empresa y sucursal, ubicaciones internas opcionales y posiciones por variante/ubicación. Evita doble contabilización entre estados.

Define formalmente existencia física, unidades bloqueadas, reservas de pedidos, asignaciones offline y tránsito. La disponibilidad central para nuevas ventas será física menos bloqueadas y compromisos activos sin solapamientos. Lo asignado a un dispositivo offline no estará simultáneamente disponible para web u otra caja. Tránsito no cuenta como disponible en destino antes de recepción.

Implementa apertura masiva idempotente, stock mínimo, conteo físico con corte, diferencias justificadas, aprobación por permiso y reversos en vez de borrar movimientos. No admitas stock negativo por defecto. Toda corrección conserva actor, motivo, fecha y referencia.

Transferencias: solicitud, preparación, despacho, tránsito y recepción parcial o total. Registra faltantes y daños sin crear unidades. Usa transacciones, bloqueos en orden consistente y restricciones SQL. Las reservas tienen vencimiento, liberación idempotente y protección frente a confirmación de pago simultánea. Las asignaciones offline vencidas requieren conciliación antes de devolverlas a disponibilidad.

Presenta kardex por producto/almacén, stock por ubicación y reportes. Prueba dos compradores por la última unidad, recepción repetida, traslado parcial, cancelación simultánea y conservación de unidades. Criterio: cantidades conciliables con movimientos y ninguna ruta que altere saldos fuera del servicio de inventario.
```

## Prompt 09 — proveedores, entradas de mercadería y costos

```text
Implementa proveedores por empresa y documentos de compra/recepción para alimentar inventario con costos verificables. Permite borrador, confirmación, recepción parcial, cierre y reverso autorizado. Una recepción confirmada genera movimientos una sola vez, incluso ante reintentos o timeouts. No permitas cambiar silenciosamente cantidades ya recibidas.

Registra proveedor, referencia del documento, fecha de recepción, almacén, variantes, unidades, costo unitario, descuentos y costos adicionales distribuidos mediante regla documentada. Separa impuestos recuperables y costos según configuración validada por el responsable del negocio; no inventes un tratamiento contable universal.

Adopta costo promedio ponderado por empresa y variante como política inicial de gestión, con un pool de costo documentado que incluya sus almacenes; las transferencias internas no generan utilidad ni cambian arbitrariamente el costo. Registra un snapshot de costo de venta al consumir existencias. Documenta tratamiento de devoluciones y movimientos retroactivos; estos no deben recalcular reportes pasados silenciosamente.

Si falta costo, marca el producto o venta con información incompleta y excluye esa parte de una utilidad afirmada como definitiva. Diferencia costo de referencia del catálogo y costo aplicado a ventas. Explica que se trata de control gerencial, no de contabilidad oficial completa.

Prueba recepción parcial, costo promedio exacto, confirmación duplicada, transferencia y devolución. Criterio: las entradas modifican existencias con respaldo documental y los reportes pueden reconstruir de dónde salió el costo usado.
```

## Prompt 10 — motor de precios, promociones y cupones

```text
Implementa un servicio central de cotización usado por tienda, POS y pedidos. Soporta descuento porcentual, importe fijo, precio promocional por variante, promociones por categoría y cupones con vigencia, compra mínima, límite total y por cliente cuando sea verificable. Define prioridad, elegibilidad, combinabilidad y canal. Bloquea descuentos que produzcan totales negativos.

Evalúa fechas en la zona horaria de la empresa y almacena instantes UTC. Calcula importes con precisión exacta y documenta el orden de descuentos, impuestos y reparto del descuento global entre líneas. Devuelve desglose comprensible: subtotal, descuentos, impuestos configurados, entrega y total. No hardcodees reglas fiscales no verificadas.

Los precios y descuentos del navegador nunca son autoridad. Recalcula en checkout y confirmación; conserva snapshot y versión de las reglas aplicadas. La cotización tiene vencimiento y una modificación requiere informar al cliente antes de cobrar. Controla límites de cupones con transacciones para evitar sobreuso concurrente; las cancelaciones liberan usos según política explícita.

Muestra ofertas en inicio, catálogo, ficha y carrito, con condiciones y periodo. No inventes precios anteriores ni porcentajes visuales. Crea administración con previsualización, programación y auditoría. Los descuentos manuales del cajero requieren permiso y límites configurables.

Prueba bordes de vigencia, redondeo, dos cupones incompatibles, última utilización simultánea, manipulación de precio y descuento de otra empresa. Criterio: mismo cálculo en todos los canales y explicación del total mostrada al comprador.
```

## Prompt 11 — punto de venta, ventas y caja

```text
Implementa POS conectado al catálogo, precios e inventario reales. Permite búsqueda/código, selección de variante, cantidades enteras, cliente opcional en contado, cliente identificado en crédito, descuento autorizado, pago mixto, vuelto y ticket imprimible. La impresión fallida no repite la venta. El diseño debe servir a teclado y lector de códigos que actúe como entrada de teclado.

Implementa cajas por sucursal, turno de apertura, saldo inicial, ingresos/egresos justificados, ventas, cobros y cierre con arqueo. Diferencia efectivo, Yape, transferencia y crédito; solo efectivo altera el efectivo esperado. Los pagos digitales pendientes no se muestran como cobros verificados. No permitas operaciones en una caja cerrada.

La confirmación debe guardar venta, líneas, pagos válidos, crédito si procede y consumo de stock en una transacción coherente. Usa idempotencia, bloqueos y restricciones únicas; los eventos externos salen después de commit. No produzcas una venta huérfana si falla inventario. El número interno y el número fiscal son distintos.

Incluye listado y detalle histórico con snapshots. Establece permisos para descuentos, reapertura y retiros; las anulaciones se implementarán mediante el módulo de reversos, nunca borrando ventas. Registra auditoría sin filtrar datos sensibles.

Prueba doble clic, timeout y reintento, última unidad, caja cerrada, pago parcial, pago mixto y ticket reimpreso. Criterio: una venta confirmada produce exactamente un efecto comercial, un movimiento de stock por línea y el efecto de caja que corresponda.
```

## Prompt 12 — tienda, carrito, checkout, pedidos y entrega

```text
Implementa la tienda pública de cada empresa: inicio personalizado, categorías, promociones, búsqueda con filtros/paginación, ficha con imágenes y variantes, carrito por tienda y checkout. El usuario puede navegar sin login y comprar como invitado o con cuenta. No mezcles carritos de empresas diferentes. La cuenta no comparte automáticamente datos comerciales entre negocios.

Solicita nombre, teléfono, correo y datos necesarios para entrega o recojo. Usa dirección, distrito/zona y referencias; ubicación en mapa será opcional y no exigirá permisos de geolocalización para comprar. Configura zonas atendidas, tarifas, horarios y puntos de recojo por empresa. Una tarifa sin configurar no equivale a entrega gratuita: permite solicitar cotización o impide confirmar el total definitivo, según política visible.

Crea el pedido antes de abrir WhatsApp, con código legible y un identificador interno no predecible. Guarda snapshots, cotización, estado del pago y estado de entrega por separado. Reserva existencias con vencimiento y evita descuento doble al convertir reserva en venta/despacho; documenta el punto exacto del ciclo. La preparación de pedidos atiende su sucursal/almacén autorizado.

Para invitados, entrega seguimiento mediante enlace secreto aleatorio de alta entropía, caducable/revocable y tratado como credencial; no bastará adivinar el código. Evita incluir datos personales en URLs, analítica o logs. Para clientes autenticados verifica propiedad y tenant.

Prueba precio manipulado, carrito ajeno, stock agotado, reserva vencida, cambios de entrega y pedido duplicado. Criterio: un comprador completa un pedido real y el administrador lo gestiona desde su panel.
```

## Prompt 13 — pagos, constancias, créditos y abonos

```text
Implementa medios de pago configurables por empresa: efectivo, Yape, BCP/transferencia y adaptadores futuros. Permite número/titular/QR e instrucciones validadas; no muestres métodos incompletos como utilizables. Mantén historial de cambios en cuentas de cobro y reautenticación para modificarlas. Las credenciales de integraciones se cifran en servidor y no llegan al cliente.

El comprador puede adjuntar constancia con límites, comprobación real de tipo y almacenamiento privado en cuarentena cuando sea necesario. Una imagen u OCR no prueba pago: comienza pendiente y requiere validación autorizada o confirmación verificable de proveedor. Controla referencia, importe, moneda, empresa y posible reutilización; el falso positivo requiere revisión, no borrado automático.

Modela pagos y sus aplicaciones a pedidos/ventas para soportar contado, crédito y abonos. Ejemplo obligatorio: venta S/150, abono S/50, deuda S/100; ventas reportan S/150, cobros S/50 y cuentas por cobrar S/100. Define vencimiento, límite de crédito y permisos. El crédito identifica al cliente y no se concede anónimamente.

Protege confirmaciones con idempotencia y control de concurrencia. Si se añaden webhooks reales, exige firma verificable, protección contra replay y verificación servidor-servidor compatible con el proveedor. Una URL de retorno del navegador no confirma pago. Resuelve el pago recibido después del vencimiento de reserva mediante excepción visible, sin prometer stock inexistente.

Prueba constancia duplicada, confirmación simultánea, abono repetido, exceso de aplicación y acceso ajeno. Criterio: deuda, cobros y pagos verificables concilian sin mezclar dinero de tiendas diferentes.
```

## Prompt 14 — devoluciones, cancelaciones y conciliación

```text
Implementa cancelación de pedidos, devolución parcial/total y reembolso con permisos y motivo. Usa estados y documentos de reverso; nunca borres una venta confirmada ni edites su historial. Separa tres hechos: devolución comercial, reintegro físico de mercadería y devolución de dinero. Pueden ocurrir en momentos diferentes.

Una devolución inspecciona condición del producto y lo envía a disponible, bloqueado/dañado o pendiente de revisión. Solo unidades realmente recibidas regresan al stock físico; una reserva cancelada únicamente libera disponibilidad. La devolución parcial no podrá superar lo vendido menos lo ya devuelto. Conserva costo y descuentos proporcionales según reglas documentadas.

Los reembolsos requieren aprobación y constancia verificable de ejecución. No marques devuelto dinero por pulsar un botón si falta el pago real. En crédito, aplica notas o reversos al saldo con trazabilidad y sin generar deudas negativas no explicadas. Las correcciones fiscales se delegan al módulo fiscal con el documento correspondiente, no con una eliminación.

Crea reportes de conciliación: ventas vs. aplicaciones de pago, cobros vs. caja por método, movimientos vs. posiciones, pedidos vs. reservas y fiscal vs. ventas. Las diferencias generan incidencias investigables y acciones autorizadas, sin arreglos automáticos destructivos.

Prueba dos devoluciones simultáneas de la misma unidad, reembolso reintentado, pedido con pago tardío, mercadería dañada y devolución tras traslado. Criterio: reversos consistentes y reconstrucción completa de dinero, unidades y responsables.
```

## Prompt 15 — WhatsApp por negocio y coordinación del pedido

```text
Implementa contacto WhatsApp independiente por empresa y, opcionalmente, por sucursal. Valida formato internacional del número y autorización para cambiarlo. Nunca hardcodees el teléfono de Santiago como número de todas las tiendas.

Primera modalidad: enlace oficial click-to-chat con texto codificado correctamente que incluya referencia del pedido, productos, variantes, cantidades, importe y enlaces públicos de producto. El cliente pulsa y envía el mensaje; abrir el enlace no demuestra envío, entrega, pago ni confirmación. Evita meter direcciones, correos o enlaces secretos de seguimiento en el mensaje por defecto; el cliente puede compartir información adicional conscientemente.

Aclara en la interfaz y documentación que el enlace no adjunta por sí mismo la foto como archivo. Ofrece enlaces públicos a las fichas/imágenes. Prepara una segunda modalidad opcional mediante WhatsApp Business Platform para mensajería multimedia, con adaptador mantenido y requisitos vigentes verificados: identidad empresarial, destinatarios, consentimiento, ventanas/plantillas cuando apliquen y credenciales. No uses SDK archivado solo porque aparece en una búsqueda.

El pedido ya debe existir y permanecer visible en administración aunque no se envíe ningún mensaje. Usa outbox para envíos automáticos, deduplicación, reintentos, límites por empresa y estados reales del proveedor. Valida autenticidad de webhooks y no confíes en eventos falsificados. Ningún mensaje real se envía desde pruebas.

Prueba número de otra tienda, codificación de texto, pedido guardado antes de redirigir, modo sin credenciales y deduplicación del adaptador. Criterio: coordinación útil sin fingir adjuntos automáticos ni estados de negocio basados en abrir WhatsApp.
```

## Prompt 16 — tickets y comprobantes electrónicos SUNAT

```text
Implementa un dominio fiscal desacoplado de venta, pago y entrega. Genera ticket interno imprimible con identidad del negocio y detalle de operación; si no es comprobante electrónico válido, identifícalo claramente como documento interno sin fingir aceptación tributaria.

Verifica documentación oficial SUNAT vigente para la modalidad elegida. Diseña FiscalProvider con contratos y un adaptador real compatible: integración directa o proveedor autorizado elegido y documentado. No intentes construir un OSE ni afirmes homologación del SaaS por tener un endpoint de pruebas. Si falta elección o credenciales, completa dominio, cola, validadores y pruebas contractuales; deja explícito qué conexión real está pendiente.

Cada empresa usa su RUC, establecimiento, series, correlativos, certificado y credenciales propios. Protege certificados y claves con cifrado, accesos mínimos, auditoría y plan de rotación. No los sirvas desde almacenamiento público ni los compartas entre tenants. Conserva XML, respuesta/CDR cuando corresponda, representación y trazabilidad con permisos.

Modela creado, pendiente, enviado, aceptado, observado/rechazado y error según respuestas oficiales; no supongas que HTTP 200 significa aceptación. Evita duplicar correlativos ante reintentos y timeouts; consulta estado antes de reemitir. Incluye notas de crédito y bajas/correcciones según reglas verificadas. Parametriza tasas y catálogos con vigencia.

Para POS offline, separa registro comercial local y emisión/transmisión fiscal: no declares que el ticket offline tiene aceptación. Documenta contingencias y plazos solo tras verificarlos oficialmente. Prueba con dobles de prueba y sandbox autorizado; nunca envíes documentos reales durante desarrollo. Criterio: estados honestos y conexión real pendiente identificada sin certificados inventados.
```

## Prompt 17 — CRM, reportes y ganancias fundamentadas

```text
Implementa clientes por empresa, datos de contacto necesarios, direcciones, historial de pedidos/ventas, frecuencia de compra, última compra, gasto acumulado y cuentas por cobrar. Evita duplicados con revisión: un teléfono o correo compartido no autoriza fusionar dos personas automáticamente. El cliente autenticado solo consulta su perfil y operaciones autorizadas de esa tienda.

Añade segmentación útil por recurrencia, inactividad y deuda. Cualquier preferencia de contacto o marketing debe estar registrada; no envíes campañas sin habilitación y consentimiento aplicables. Proporciona procesos de exportación, corrección y eliminación/anonimización sujetos a las obligaciones de conservación verificadas; no borres documentos transaccionales silenciosamente.

Dashboard diario, semanal, mensual, anual y por rango con zona horaria empresarial. Filtra por sucursal, almacén, vendedor, producto y canal. Distingue ventas brutas, descuentos, devoluciones, ventas netas, cobros verificados, deuda, costo de ventas, utilidad bruta, gastos registrados y resultado gerencial. Documenta fórmulas, impuestos y corte de fechas. No llames utilidad neta contable a una resta incompleta.

Si faltan costos/gastos, muestra cobertura y resultado parcial; no inventes cero. Calcula rentabilidad con snapshots de costo y evita que cambiar el catálogo reescriba el pasado. Crea reportes de stock, valorización, movimientos y productos de baja disponibilidad. Exportaciones privadas, temporales, tenant-aware y protegidas contra fórmulas CSV.

Prueba conciliación con ventas y pagos conocidos, periodos en America/Lima, costos incompletos y exportación ajena. Criterio: cada indicador puede explicarse y reconstruirse desde registros de origen.
```

## Prompt 18 — POS sin internet y sincronización controlada

```text
Implementa offline exclusivamente para POS en dispositivos registrados, con PWA, service worker e IndexedDB; la tienda web necesita conexión para confirmar pedidos. Usa HTTPS en despliegue y documenta el tratamiento de localhost. No caches páginas administrativas sensibles, credenciales, constancias ni perfiles completos mediante reglas amplias.

Antes de desconectar, el servidor entrega catálogo/precios autorizados, caja, permiso offline limitado y asignación de unidades por dispositivo. Esa asignación compromete disponibilidad central para que web u otra caja no vendan las mismas unidades. No permitas emitir asignaciones nuevas sin conexión. Limita duración y operaciones; el primer login, MFA, cambios de permisos, créditos nuevos, devoluciones y confirmación digital requieren conexión en la política inicial.

Solo efectivo confirmado localmente; pagos digitales pueden registrarse como pendientes, nunca verificados. Guarda snapshots y UUID de operación, dispositivo, empresa, caja, secuencia, versión y tiempos local/servidor. No confíes exclusivamente en el reloj del equipo. Evita duplicados en varias pestañas mediante transacciones IndexedDB y coordinación compatible. Muestra saldo de unidades y cola pendiente.

Sincronización: valida identidad y dispositivo, pertenencia, permiso concedido, asignación, integridad, límites e idempotencia; usa restricciones únicas por empresa/dispositivo/operación. Procesa lotes con acuses por operación, backoff y recuperación tras respuesta perdida. Conflictos quedan en revisión, sin descartar ventas ni cambiar importes silenciosamente.

Una asignación vencida no vuelve automáticamente a venta central hasta conciliar lo consumido; documenta revocación diferida e imposibilidad de conocer instantáneamente actividad de un equipo desconectado. No prometas durabilidad absoluta del navegador: solicita persistencia, detecta cuotas y ofrece recuperación/exportación segura de pendientes sin secretos. Logout bloquea acceso sin borrar ventas no sincronizadas.

Prueba desconexión, reinicio, doble envío, reloj cambiado, sesión expirada, dos pestañas, equipo revocado y última unidad. Criterio: ventas pendientes recuperables y conciliables, sin doble venta del stock asignado.
```

## Prompt 19 — administración SaaS, empresas y planes

```text
Implementa el área /plataforma con propietario de plataforma y MFA obligatorio. Permite alta de empresa, invitación a su administrador, activación/suspensión y configuración de planes con límites de usuarios, sucursales, productos, almacenamiento y módulos. Los límites se validan en servidor y bajo concurrencia, no solo ocultando botones.

Separa las suscripciones que una empresa paga por usar el SaaS de los pagos que sus compradores hacen por productos. Usa tablas, referencias y credenciales diferenciadas. La primera versión puede llevar activación y cobro del plan verificados manualmente; si no hay proveedor contratado, no inventes pagos recurrentes automáticos. Deja contrato/adaptador y estados explícitos para futura integración.

Define prueba, activo, vencido, gracia y suspendido. Una suspensión debe detener nuevas operaciones según política documentada sin borrar datos, perder ventas offline pendientes ni impedir una conciliación administrativa autorizada. Un cambio a plan menor no elimina productos o usuarios automáticamente; muestra exceso y ruta de regularización.

El soporte ve métricas operativas mínimas. Acceso excepcional a datos comerciales requiere permiso específico, motivo, duración, reautenticación y auditoría; no implementes un bypass RLS abierto ni suplantación invisible. Nunca muestres contraseñas o secretos empresariales al superadministrador.

Prueba alta de dos empresas, invitaciones, límites concurrentes, vencimiento, downgrade y negación de acceso comercial no autorizado. Criterio: administrar muchas empresas desde una plataforma manteniendo su independencia y sin confundir sus cobros.
```

## Prompt 20 — infraestructura, secretos y endurecimiento

```text
Prepara infraestructura reproducible de desarrollo, staging y producción sin desplegar aún. Usa Dockerfiles multietapa e imágenes verificadas con versiones/digests registrados. Separa proxy web, PHP, worker, scheduler, PostgreSQL y Redis. Solo el proxy expone tráfico público; base, caché y almacenamiento administrativo quedan en redes privadas. En local, los puertos de diagnóstico se enlazan exclusivamente a loopback.

Ejecuta procesos con usuario no root, capacidades mínimas, permisos de archivos restrictivos y sistema de archivos de solo lectura donde sea viable. Monta únicamente directorios de escritura necesarios. No montes el socket Docker dentro de la aplicación. No incluyas .env, claves, herramientas de desarrollo o credenciales de build en la imagen final.

Configura TLS, redirección HTTPS, trusted hosts/proxies explícitos, cookies seguras, APP_DEBUG=false en producción, límites de cuerpo/tiempos y errores sin trazas públicas. Implementa CSP compatible con nonces cuando sea necesario, frame-ancestors y headers apropiados. Activa HSTS solo tras validar dominio y TLS. No permitas CORS comodín con credenciales.

Define secretos por entorno y empresa, rotación y recuperación. No regeneres APP_KEY al actualizar: rompería datos cifrados. Documenta rotación controlada y copia segura de claves. Para archivos privados, autorización previa y enlaces firmados de corta duración; para fotos públicas, solo variantes publicadas y sin documentos privados.

Aplica límites de recursos/cuotas, timeouts y allowlists de destinos externos para reducir SSRF y agotamiento. Configura escaneo de archivos privados cuando sea viable, con cuarentena y sin afirmar que un escáner garantiza inocuidad.

Prueba que PostgreSQL/Redis no están expuestos, debug apagado, cookies y headers correctos y secretos ausentes de bundle/imágenes. Criterio: configuración productiva documentada, sin aplicar cambios a servidores reales.
```

## Prompt 21 — auditoría, observabilidad y recuperación

```text
Implementa observabilidad con logs estructurados, identificador de petición, tenant y actor cuando corresponda. Redacta contraseñas, tokens, sesiones, certificados, constancias y datos personales innecesarios. No uses una URL secreta de seguimiento como campo de log. Define retención, acceso y alertas.

Audita permisos, cuentas de cobro, configuración fiscal, ajustes de stock, anulaciones, devoluciones, reembolsos, publicación de tienda y acceso excepcional de soporte. Los registros de negocio no serán editables por usuarios normales. Explica límites del almacenamiento local de auditoría frente a un administrador de infraestructura y prepara envío a destino independiente para mayor resistencia a alteración.

Métricas: errores, latencia, cola y fallos, pagos pendientes, antigüedad de sincronizaciones, dispositivos offline sin conciliar, reservas, diferencias de inventario y documentos fiscales pendientes. Health/liveness y readiness no exponen detalles internos al público. Documenta respuestas operativas y escalamiento.

Diseña copias cifradas de PostgreSQL, objetos/documentos, configuración y claves necesarias para restaurar. Define como objetivos iniciales sujetos a capacidad y pruebas: RPO hasta 15 minutos para base mediante WAL/PITR y RTO hasta 4 horas; no afirmes lograrlos hasta medir. Mantén copia fuera del host y credenciales de respaldo distintas del runtime. Incluye retención y verificación de integridad.

Ejecuta una restauración en entorno aislado, valida dos empresas, documentos, saldos y acceso; nunca sobrescribas la base de trabajo. Documenta restauración por empresa en infraestructura compartida mediante recuperación aislada y extracción controlada, no restauración total encima de producción.

Criterio: evidencia de restauración real o pendiente explícito si falta infraestructura; runbooks reproducibles de incidentes, pérdida de datos y rotación de secretos.
```

## Prompt 22 — CI, seguridad y pruebas de concurrencia

```text
Configura CI sin depender de secretos de producción. Ejecuta instalación desde lockfiles, formato, análisis estático PHP, lint/typecheck frontend, build, pruebas de dominio, integración PostgreSQL y E2E seleccionadas. Las pruebas RLS usan el rol runtime real y no una conexión de superusuario que anule las políticas. Distingue bases local, testing y staging con salvaguardas contra borrado accidental.

Añade revisión de dependencias Composer/npm, detección de secretos y análisis de configuración/imagen. Documenta herramienta, versión, alcance y limitaciones; un resultado limpio no equivale a seguridad total. Genera inventario de componentes/SBOM cuando sea viable. Las excepciones de vulnerabilidad requieren motivo, alcance y fecha de revisión; no ocultes fallos con exclusiones generales.

Crea matriz de requisitos aplicables de OWASP ASVS nivel 2 con implementación, prueba y pendientes. Evalúa autorización/IDOR, CSRF, XSS almacenado, SQL injection, SSRF de imágenes/integraciones, archivos peligrosos, exportaciones, fuerza bruta, abuso de checkout, MFA, webhooks, aislamiento en caché/jobs y acceso a documentos privados.

Pruebas concurrentes reales: última unidad entre POS y web, última utilización de cupón, abono repetido, devolución simultánea, reserva vencida con pago entrante y replay de venta offline. Usa conexiones/procesos independientes y condiciones de carrera reproducibles; no simules concurrencia con dos llamadas secuenciales.

Realiza carga local/staging con volumen y recursos declarados; informa p50/p95, errores y límites medidos, sin prometer usuarios ilimitados. Los escaneos activos se limitan al entorno propio de pruebas.

Criterio: checks automatizados útiles, fallos corregidos y evidencia reproducible. No declares listo para producción si siguen abiertos fallos críticos de autorización, dinero o inventario.
```

## Prompt 23 — aceptación integral y demostración de dos negocios

```text
Ejecuta aceptación funcional completa con datos explícitos de demostración en local/testing: empresa A vende ropa con tallas/colores y empresa B golosinas por unidades. Usa dos almacenes de A, al menos una sucursal por empresa y roles de administrador, cajero, almacenero y comprador. No uses datos personales ni cuentas de cobro reales.

Recorrido A: importar catálogo, abrir stock, recibir compra con costo, transferir parcialmente, publicar tienda, programar oferta y cupón, comprar como invitado, reservar unidades, adjuntar constancia, verificarla como acción de prueba, preparar/entregar, consultar ticket y estado fiscal simulado claramente separado. Abrir WhatsApp solo como previsualización/enlace, sin enviar mensajes reales.

Recorrido POS: abrir caja, vender al contado, vender S/150 con S/50 de abono y S/100 de deuda, cobrar después, devolver parcialmente un artículo apto y otro dañado, cerrar caja y conciliar. Comprueba snapshots, costo, promociones, disponibilidad y reportes de ventas/cobros/deuda.

Recorrido offline: asignar unidades, desconectar, vender, reiniciar navegador, reconectar y enviar dos veces la misma operación. Demuestra un único efecto, pendientes conservados y resolución de asignaciones. Repite accesos de A contra B en vistas, API, archivos, exportaciones y jobs.

Prueba tienda móvil, navegación por teclado, variantes, seguimiento seguro de invitado, edición/publicación, cuenta cliente y login administrativo. Comprueba que el administrador de B no ve reportes de A.

Entrega matriz de aceptación por requisito: aprobado, fallido o no ejecutado, con comandos y evidencia. Corrige fallos encontrados y repite solo recorridos afectados. Criterio: flujos completos conectados a base real, no únicamente pantallas o contadores de pruebas.
```

## Prompt 24 — cierre técnico y preparación para conectar/publicar

```text
Revisa el proyecto completo y prepara una entrega local reproducible y lista para completar conexiones externas. No publiques, cambies DNS, crees servidores, envíes mensajes, emitas comprobantes reales ni generes instaladores en esta etapa.

Desde una copia limpia de código y una base aislada ejecuta instalación, migraciones, build y verificaciones críticas siguiendo únicamente README. Prueba actualización con datos existentes, respaldando antes y evitando migraciones destructivas. Prepara cambios de esquema compatibles mediante expand/contract cuando corresponda. El rollback de código no debe revertir a ciegas migraciones ni perder ventas.

Entrega manual breve de administrador, cliente, cajero y almacenero; comandos exactos para iniciar/detener app, workers y scheduler; creación segura del primer propietario sin contraseña universal; gestión de MFA y restauración. Documenta arquitectura, roles, tablas, política offline y límites medidos.

Completa docs/EXTERNAL_CONNECTIONS.md con cada dependencia: requerida/opcional, estado real, responsable, datos faltantes, ubicación segura de secretos, prueba sandbox, prueba de conexión, validación productiva y reversión. Incluye dominio/TLS, hosting, correo, almacenamiento, respaldos externos, SUNAT/proveedor, Yape/BCP manuales o API contratada, WhatsApp link y API opcional. No inventes endpoints bancarios ni credenciales de ejemplo que parezcan reales.

Prepara despliegue por etapas: staging con datos sintéticos, smoke tests, respaldo, despliegue controlado, migración compatible, recarga de procesos, comprobación de salud y plan de rollback. Una prueba de conexión real solo se marca exitosa con resultado observado.

Informa qué está implementado y probado, qué solo está preparado y qué falta contratar/configurar/validar. Enumera bloqueos materiales y riesgos residuales con evidencia. No digas que basta conectar si faltan pruebas o integraciones. Criterio: entrega honesta, segura y revisable para autorizar después una publicación concreta.
```

## Si una etapa falla: prompt de corrección

```text
No avances al siguiente módulo. Lee los errores de la etapa actual y reproduce el fallo en el entorno adecuado. Identifica causa raíz, corrige la implementación y agrega o ajusta una prueba que verifique el comportamiento esperado, sin debilitar controles ni modificar expectativas para ocultar el problema. Conserva datos y cambios ajenos. Ejecuta las pruebas afectadas y las verificaciones críticas relacionadas. Actualiza docs/PROGRESS.md con evidencia, limitaciones y estado real. Si falta una dependencia externa, completa lo independiente y registra el bloqueo exacto; no simules una integración productiva exitosa.
```

## Reglas para evaluar el resultado del asistente de código

- Una pantalla terminada no implica que el flujo esté terminado: debe persistir datos y aplicar autorización.
- Tener tenant_id no demuestra aislamiento: deben existir pruebas cruzadas también para archivos, colas, consultas y caché.
- Tener HTTPS, UUID o cifrado no garantiza seguridad por sí solo.
- Una captura de Yape/BCP no valida automáticamente un pago.
- Abrir WhatsApp no confirma envío, pago ni entrega.
- Un ticket no demuestra aceptación fiscal y una respuesta HTTP no reemplaza la respuesta fiscal correspondiente.
- Un saldo de stock correcto en una prueba secuencial no demuestra corrección con dos ventas simultáneas.
- Los datos pendientes offline no son un respaldo: el navegador puede perder almacenamiento y un equipo desconectado no recibe revocaciones instantáneas.
- “Listo para conectar” debe identificar conexiones pendientes y no ocultar implementación faltante.

## Referencias técnicas oficiales consultadas

Las fuentes respaldan las capacidades y precauciones técnicas; la arquitectura y la secuencia son una propuesta de ingeniería para los requisitos del proyecto. Verificar documentación vigente al ejecutar cada integración.

1. Laravel 13, notas y soporte: https://laravel.com/docs/13.x/releases
2. Laravel, starter kits y autenticación: https://laravel.com/docs/13.x/starter-kits
3. Laravel, despliegue: https://laravel.com/docs/13.x/deployment
4. PostgreSQL, Row Security Policies: https://www.postgresql.org/docs/18/ddl-rowsecurity.html
5. OWASP, ASVS: https://owasp.org/projects/asvs
6. OWASP, Multi Tenant Security: https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html
7. MDN, service workers: https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers
8. MDN, IndexedDB: https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API/Using_IndexedDB
9. SUNAT, SEE del Contribuyente: https://cpe.sunat.gob.pe/sistema_emision/see_contribuyente
10. SUNAT, certificado digital: https://cpe.sunat.gob.pe/certificado-digital
11. WhatsApp, click-to-chat: https://faq.whatsapp.com/5913398998672934
12. Meta, documentación de WhatsApp Cloud API: https://developers.facebook.com/docs/whatsapp/cloud-api/overview

## Orden de ejecución

| Etapa | Prompt | Resultado esperado |
| --- | --- | --- |
| Base | Maestro + 01–04 | Proyecto, datos, identidad y aislamiento verificados. |
| Experiencia y catálogo | 05–07 | Interfaz por rol, editor y productos masivos. |
| Operación comercial | 08–14 | Almacenes, costos, promociones, POS, pedidos, pagos y reversos. |
| Integraciones y análisis | 15–17 | WhatsApp, dominio fiscal, CRM y reportes con estados honestos. |
| Offline y SaaS | 18–19 | Venta desconectada controlada y gestión de empresas/planes. |
| Infraestructura y validación | 20–24 | Endurecimiento, recuperación, CI, aceptación y entrega preparada. |

Son **24 etapas de implementación más el prompt maestro**. Es una secuencia inicial de trabajo, no una garantía de duración: las correcciones o decisiones externas pueden requerir ejecuciones adicionales.
