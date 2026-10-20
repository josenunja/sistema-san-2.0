# Estado verificable — 17 de octubre de 2026

## Entrega local

El sistema ya supera la fundación inicial. Implementa gestión comercial local con datos persistentes en PostgreSQL. No se declara la terminación íntegra de los 24 prompts ni despliegue productivo.

| Área | Implementado | Validación / límite |
|---|---|---|
| Acceso y aislamiento | Instalación inicial, MFA, roles, invitaciones de uso único, bloqueo último propietario, FORCE RLS y contexto por transacción | Pruebas negativas con sas_runtime, lectura/escritura ajena y limpieza del contexto |
| Catálogo | Variantes por SKU, grupo, categoría, fotos WEBP, búsqueda/paginación, CSV preview/confirmación/exportación | Pruebas de importación repetida, céntimos, fórmula CSV, foto y empresa ajena |
| Tienda | Configuración versionada, borrador/vista previa/publicación/restauración, carrito por empresa, ficha y galería | Compilación y compra ficticia real en navegador |
| Inventario | Almacenes, recepciones, pool de costo, reservas, transferencias parciales/dañadas, bloqueo, conteo con corte | Pruebas de conservación; competencia real de dos procesos por última unidad |
| Compras | Proveedores, borrador por variante, confirmación, recepción parcial, cierre y reverso compensatorio | Prueba de reintento, recepción única y restauración de costo/unidades |
| Precios | Motor compartido, porcentaje o fijo, categoría/variante/canal, vigencia, mínimo, usos y límite por cliente | Reparto exacto de céntimos, límite individual y precio manipulado probados; sin promociones automáticas ni precio programado por variante |
| POS y caja | Venta, caja personal por almacén, ingresos/retiros, arqueo, gastos, crédito con límite y vencimiento | Caso S/150, abono S/50, deuda S/100 y cobro posterior; sin pago mixto atómico ni reapertura |
| Pedidos | Reserva 30 minutos, expiración programada, estados de pago/entrega separados, seguimiento secreto y cuenta de comprador | Navegador: pedido de S/35 persistido, una unidad reservada y seguimiento sin secreto en URL |
| Pagos y reversos | Efectivo, Yape/BCP manual pendientes, constancias privadas, revisión, cancelación, devolución parcial y reembolso manual | Referencias, inmutabilidad, sobredevolución y rollback probados; no transferencias bancarias automáticas |
| Clientes e informes | Perfil independiente por tienda, consentimiento, crédito, reportes por fecha Lima, costos snapshot, gastos y cobertura incompleta | Sin CRM avanzado/retención legal automatizada ni filtros analíticos completos |
| WhatsApp | Enlace individual por tienda con pedido, variantes, importes y fichas públicas | No envío automático ni adjunto de imagen; abrirlo no prueba envío/pago |
| Fiscal | Ticket claramente interno, documento unconfigured y contrato FiscalProvider con proveedor desactivado | Pruebas impiden aceptación sin evidencia. Proveedor y credenciales aún inexistentes; no sandbox fiscal real |
| Plataforma | Dueño con MFA, empresas, activación/suspensión manual y planes con cuotas de productos/almacenes/usuarios | Sin cobro recurrente, estados trial/gracia automáticos, cuotas de almacenamiento/módulos ni soporte delegado |
| Offline | PWA, asignación exclusiva, precios autorizados, plazo, cola cifrada, secuencia, idempotencia, revisión exacta, conciliación y recuperación | Pruebas del dominio aprobadas; aceptación completa de desconexión, reinicio, múltiples pestañas y fallas de cuota en equipos reales pendiente |
| Recuperación | Copia cifrada autenticada de DB/configuración/archivos, restauración a base nueva | Restauración final en 4,21 s de base pequeña: 13 empresas, 23 productos, 11 pedidos, 39 movimientos y 2 ventas; saldos y un archivo privado idénticos, RLS conservado. Sin PITR/WAL ni copia remota |
| Calidad y seguridad | Análisis PHP, TypeScript, lint, build, CSP/Host, auditoría, CI preparado | Revisar los resultados finales después de cambios; CI remoto no ejecutado, no certificación ASVS ni prueba de carga |

## Evidencia ejecutada

- Suite específica: 36 pruebas aprobadas, 157 aserciones, PostgreSQL sas_san_test como sas_runtime. Incluye compras, promociones y contrato fiscal desactivado.
- Cinco carreras con procesos independientes aprobadas: última unidad, último uso de cupón, cobro repetido, devolución simultánea y replay offline. Cada una produjo un solo efecto comercial.
- PHPStan final sin errores; Pint aplicado y PHPStan final sin errores. Sin exclusiones ni baseline para ocultar errores.
- Frontend: TypeScript y lint aprobados, compilación con 3454 módulos. Recompilación final tras ampliar métricas de clientes aprobada.
- Verificación previa del starter: 38 aprobadas, 2 omitidas por registro público cerrado, 134 aserciones con propietario SQL solo en testing; no sustituye pruebas RLS.
- Backup cifrado/restauración ejecutados sobre sas_san_test, base nueva sas_san_restore_cb2afc6c00f4; no sobrescribió trabajo.
- Navegador: directorio de dos empresas sintéticas, tienda, carrito, cotización, pedido, seguimiento y acceso con contraseña + MFA verificados.
- Auditorías Composer/npm previas sin avisos conocidos; no auditoría integral.

## Entorno y dependencias externas

Docker Desktop no se recuperó tras reinicio autorizado: errores reales de I/O/EXT4 en su disco virtual. No se reseteó ni borró. Se preparó un entorno portátil independiente PHP 8.4.26/PostgreSQL 18.6 y una base nueva local. Iniciar con iniciar.ps1. El primer propietario real aún debe crear su cuenta y MFA en /instalacion.

El usuario no tiene proveedor fiscal. La emisión real está deshabilitada. Correo es log local; pagos manuales; WhatsApp click-to-chat. No hay publicación ni conexiones reales activadas.

## Cobertura restante de los 24 prompts

Las etapas 01–19 tienen implementación local de sus recorridos principales, con límites específicos arriba. No están completas todas sus variantes: permisos por sucursal, editor de bloques avanzado, transferencias con preparación/solicitud, compras multilínea/costos adicionales automatizados, promociones programadas, pago mixto, zonas/horarios de entrega, CRM avanzado, suscripciones con gracia y cuotas de almacenamiento.

Las etapas 20–24 quedan parciales: infraestructura productiva reproducible/verificada, secretos separados, certificados fiscales reales, correo real, observabilidad externa, PITR/copias fuera del host, CI remoto/E2E completo, matriz ASVS completa, escaneo de imagen/secretos y carga con p50/p95. No declarar producción lista ni cumplimiento de todas las etapas.

## Repetición segura

verificar.ps1 usa únicamente sas_san_test. Migraciones nuevas se ejecutan con sas_owner; plataforma:runtime repone grants. No usar migrate:fresh en sas_san. Iniciar.ps1 conserva APP_KEY y datos. Leer OPERACION.md y SECURITY.md antes de exponer o conectar servicios.

- Corrección de arranque: APP_ENV=native explícito y carga completa de .env.native evitan usar el host Docker o heredar la base de testing. Las migraciones continúan con sas_owner y el servidor con sas_runtime.

- Cierre de controles: 36 pruebas / 157 aserciones, cinco carreras independientes, PHPStan, lint/formato, TypeScript y build aprobados. /instalacion devolvió HTTP 200 con Cache-Control no-store, private en el servidor nativo de trabajo.

## Mejora visual del frontend — 17 de octubre de 2026

Se rediseñaron instalación inicial, directorio, catálogo y estructura de gestión. Identidad común verde, tipografía y espacios más claros, tarjetas, iconos del paquete existente y estados de foco. El menú de gestión se despliega en móvil; la navegación conserva los permisos por rol. El catálogo mantiene fotos reales cuando existen y usa un marcador gráfico cuando no hay foto; no inventa imágenes del producto. Los formularios mantienen sus acciones y validaciones de servidor.

Verificación: lint/formato, TypeScript y compilación aprobados. Inspección visual de instalación en escritorio y catálogo en móvil (375 px de contenido sin desbordamiento horizontal). Las credenciales y los datos reales no se introdujeron durante la revisión. No se cambiaron migraciones, pagos, inventario ni integraciones.

## Catálogo general y compra con cuenta — 19 de octubre de 2026

La ruta pública `/` reúne productos publicados de todas las tiendas activas, con búsqueda, filtro por categoría, paginación, precio, imagen y enlace a la ficha de la tienda. La lectura recorre cada empresa dentro de una transacción con contexto RLS; no amplía la política SQL ni expone columnas privadas del producto. La portada de gestión `/dashboard` continúa mostrando únicamente los negocios del usuario.

La portada no redirige a la configuración aunque la instalación todavía no tenga propietario: muestra el catálogo público y su estado vacío. La configuración administrativa inicial permanece separada en `/instalacion`.

El visitante puede explorar productos, abrir fichas y preparar el carrito sin autenticarse. La confirmación del pedido exige autenticación y un perfil de comprador vinculado a esa tienda; la interfaz conduce a registro o inicio de sesión y el controlador aplica la misma restricción en servidor. Los pedidos web quedan asociados al cliente identificado.

Verificación en la copia local: formato, lint frontend, TypeScript y compilación aprobados. PostgreSQL aislado aprobó 24 pruebas relacionadas con catálogo, plataforma y compra (131 aserciones), incluida la portada pública antes de la configuración inicial. La inspección en navegador confirmó HTTP 200 en `/`, sin redirección a `/instalacion`.

## Portada comercial animada — 19 de octubre de 2026

La portada pública incorpora un buscador principal, una franja comercial en movimiento y objetos vectoriales relacionados con moda (prenda, bolsa, etiqueta y destellos) que flotan dentro del hero. Órbitas, tarjetas y estados del catálogo usan entradas y movimientos suaves sin depender de imágenes externas. Con `prefers-reduced-motion` se eliminan las transiciones generales y las partículas conservan únicamente un desplazamiento lento solicitado para la experiencia comercial.

Verificación: formato, lint, TypeScript y compilación aprobados. Portada inspeccionada en el navegador local con el buscador, la franja y los objetos animados visibles, sin alterar el flujo de catálogo o autenticación.

## Secciones comerciales y estilo urbano — 19 de octubre de 2026

La navegación pública ahora es interactiva y filtra Todo, Mujer, Hombre, Niños, Tendencias, Promociones y Tiendas. Cada producto conserva su categoría libre y suma una sección principal (`women`, `men`, `kids` o `unisex`), una marca de tendencia y un precio anterior opcional. Un producto entra automáticamente a Promociones cuando el precio anterior es mayor que el precio vigente; ambos precios y la insignia se muestran en catálogo y ficha.

El formulario del catálogo permite gestionar esos campos a propietarios de la tienda. Un dueño de plataforma puede usarlos cuando además tiene membresía de propietario en esa empresa; el rol global por sí solo no rompe el aislamiento multiempresa. La portada añade fondo con retícula, gradientes y partículas geométricas urbanas, conservando `prefers-reduced-motion`.

Verificación: 29 pruebas relacionadas aprobadas con 175 aserciones, incluidos los cinco filtros, publicación por propietario y protección de plataforma. PHPStan, Pint, formato, lint, TypeScript y compilación aprobados. El filtro Mujer se verificó también en el navegador local y cambió el encabezado y la URL del catálogo.

## Cuenta, pedidos y acceso premium — 19 de octubre de 2026

El encabezado público reemplaza el acceso genérico por Mis pedidos, Mi cuenta y un carrito desplegable. El carrito lee únicamente los carritos de las tiendas activas guardados en la sesión del navegador y conduce al checkout de cada tienda por separado; no mezcla pedidos de empresas diferentes. Los visitantes pueden abrir estos accesos y son enviados al login cuando la acción requiere cuenta.

Las rutas `/mi-cuenta` y `/mis-pedidos` reúnen los perfiles y pedidos del usuario autenticado en todas las tiendas activas. Cada lectura se ejecuta dentro del contexto RLS de la empresa correspondiente y filtra por el `user_id` asociado al cliente; el rol global de plataforma no sustituye esa relación. El acceso conserva los flujos existentes de verificación de correo y detalle del pedido.

El login usa una superficie oscura con retícula, gradientes, partículas geométricas, animaciones suaves y adaptación móvil. Conserva clave de acceso, recuperación, contraseña y recordatorio. Se corrigieron el contraste del acceso con passkey y el separador, así como el encaje vertical en ventanas bajas; con `prefers-reduced-motion` las partículas se desplazan más lentamente.

Verificación: 34 pruebas relacionadas aprobadas con 218 aserciones, incluidas las rutas protegidas y la exclusión de pedidos de otro cliente. PHPStan, Pint, formato, lint, TypeScript y compilación aprobados. En navegador se verificaron los tres accesos, la apertura del carrito, la redirección de Mis pedidos y el login premium sin introducir credenciales.

## Alta guiada y diseños por tienda — 19 de octubre de 2026

El alta de plataforma crea la empresa y su invitación de propietario en una misma operación. La invitación conserva token hash, vigencia de 48 horas y rol de propietario controlado por servidor; el superadministrador no crea ni recibe la contraseña del vendedor y continúa sin membresía comercial automática.

Cada propietario dispone de `/gestion/{slug}/configurar`, un asistente de tres pasos para nombre, logo procesado a WEBP sin metadatos, tipo de negocio, presentación, contacto, ubicación y tema. Los diez temas Clásico, Urban Freestyle, Elegante, Femenino Chic, Boutique Pastel, Glamour, Deportivo, Calzado Premium, Minimalista y Vintage comparten catálogo, inventario y checkout: son configuración versionada, no aplicaciones duplicadas. Cada tema tiene identidad cromática, tipográfica, animación de entrada y partículas propias. Un borrador crea una vista previa privada; publicar actualiza la identidad pública. La configuración previa de entrega y cobro se fusiona para no perderse al rediseñar. La paginación usa ahora estados activo e inactivo con contraste explícito para impedir números blancos invisibles.

Verificación local: 41 pruebas relacionadas aprobaron con 282 aserciones bajo PostgreSQL `sas_san_test` y `sas_runtime`; incluye los diez temas permitidos, rechazo de identificadores desconocidos, borrador privado, denegación a un miembro que no es propietario, preservación de ajustes existentes al publicar y cinco carreras independientes sin doble efecto comercial. PHPStan, Pint, formato/lint, TypeScript y compilación aprobaron. En navegador se comprobaron diseños representativos con datos sintéticos y se verificaron sus partículas activas. No implica envío real de correo ni despliegue público.

## Variantes explícitas y escaparate multiempresa — 19 de octubre de 2026

Cada SKU vendible conserva ahora talla y color explícitos, además del grupo de variantes existente. El inventario sigue contabilizado por `product_id`, por lo que dos tallas o colores mantienen existencias, reservas y movimientos independientes. Catálogo, ficha, portada general, exportación y CSV muestran o transportan los nuevos atributos; el importador continúa aceptando el formato anterior de seis columnas.

La portada `/` expone productos publicados y tarjetas de todas las tiendas activas, con filtros comerciales y enlace a cada catálogo. La aplicación no impone hoy un máximo global de tiendas; los límites se aplican por plan a productos, almacenes y usuarios. La capacidad productiva global no se afirma porque falta una prueba de carga y la agregación actual del escaparate recorre las tiendas activas una por una.

Verificación local: 42 pruebas relacionadas aprobaron con 301 aserciones en PostgreSQL `sas_san_test`, incluidas talla/color, stock independiente de dos variantes y cinco carreras de concurrencia. PHPStan, Pint, formato/lint, TypeScript y compilación aprobaron. Las migraciones incrementales se aplicaron tanto a `sas_san_test` como a la base nativa, sin reinicializar datos.

## Personalización comercial ampliada — 19 de octubre de 2026

El propietario puede cargar logo y portada procesados a WEBP, escoger colores principal/acento, ordenar las seis secciones comerciales y publicar redes sociales, horario semanal y datos de contacto. Puede activar de forma independiente recojo, delivery local, courier y envío nacional con tarifas en céntimos; la tarifa y el método elegidos quedan registrados en cada pedido. Un cupón creado en Promociones puede destacarse en la portada mientras esté vigente.

Cada tienda puede reservar un hostname único. La lista de hosts permitidos se limita al host de plataforma, entorno local o dominio de una tienda activa; la raíz de un dominio configurado resuelve su catálogo bajo el mismo aislamiento RLS. La aplicación no configura DNS ni emite certificados: propiedad del dominio, TLS, cookies y proxy continúan siendo trabajo obligatorio de despliegue.

Verificación local: 44 pruebas relacionadas aprobaron con 329 aserciones, incluida la resolución por dominio, archivos de marca, configuración completa y tarifa independiente de courier. Las cinco carreras de concurrencia, PHPStan, Pint, formato/lint, TypeScript y compilación aprobaron. La migración incremental se aplicó a `sas_san_test` y `sas_san` sin borrar datos.

## Checkout guiado, seguimiento y notificaciones — 19 de octubre de 2026

El carrito público muestra ahora el recorrido Carrito, Datos y entrega, y Pago. El comprador puede validar el cupón y revisar el total antes de crear su cuenta; la confirmación sigue exigiendo cliente autenticado y vuelve a calcular precios, descuento y entrega en servidor.

Cada creación de pedido, constancia, verificación o rechazo de pago, confirmación, cancelación, vencimiento y avance de entrega genera un evento inmutable aislado por empresa. El detalle del pedido presenta esos eventos como línea de tiempo y explica el siguiente paso al comprador. Las cuentas verificadas reciben avisos internos en `/mis-pedidos`, pueden abrir el pedido correspondiente y marcar todos sus avisos como leídos. Esta entrega no activa correo, SMS ni WhatsApp automáticos.

Verificación local: 48 pruebas aprobaron con 301 aserciones, incluidas la línea de tiempo completa, notificaciones restringidas al usuario, lectura de avisos y cinco carreras de concurrencia. PHPStan, Pint, formato/lint, TypeScript, compilación y revisión visual del checkout aprobaron. La migración incremental se aplicó a `sas_san_test` y `sas_san` sin reinicializar datos.
