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
