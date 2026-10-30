# Seguridad: implementación y límites

El entorno entregado es local. No se certifica ASVS nivel 2 ni aptitud productiva.

| Control | Implementación / evidencia | Límite |
|---|---|---|
| Autenticación | Fortify, sesiones, CSRF, verificación de correo, MFA obligatorio en gestión y plataforma | Correo local por log; revisar recuperación y fuerza bruta en staging |
| Autorización | Membresía por empresa, roles owner/cashier/warehouse; plataforma sin acceso comercial automático | Permisos por sucursal específicos aún no implementados |
| Aislamiento SQL | FORCE RLS, sas_runtime sin superusuario/BYPASSRLS, contexto SET LOCAL por transacción | RLS no evita una inyección que pueda cambiar app.tenant_id; bootstrap global aún requiere reducir privilegios productivos |
| Dinero / stock | Céntimos enteros, transacciones, bloqueo por empresa, idempotencia con hash, libros inmutables | Serialización por empresa prioriza consistencia; capacidad no medida |
| Navegador | CSP con nonce, frame-ancestors, nosniff, no-referrer, validación de Host base o dominio único de tienda activa | style unsafe-inline por temas; DNS, certificados TLS, cookies seguras/HSTS y prueba de propiedad del dominio requieren despliegue verificado |
| Archivos | Fotos decodificadas/reconvertidas WEBP; tamaño/dimensiones limitados; constancias privadas con autorización y descarga | No hay antivirus/quarantena automática instalada; no asumir inocuidad de PDF |
| CSV | Preview y confirmación, cuota, acceso privado, escape contra fórmulas | Exportación acotada a 10.000 filas; no es exportación ilimitada |
| Secretos | .env/.env.native/.runtime ignorados; resultados sensibles cifrados; tokens de invitación/seguimiento guardados como hash | Owner y runtime usan contraseña común SOLO en este entorno local; separar en producción |
| Auditoría | Operaciones, reversos, publicaciones y permisos; triggers de libros rechazan modificación | Administrador de infraestructura puede alterar DB; no hay réplica de auditoría externa |
| Offline | Stock exclusivo, plazo, secuencia única, cola AES-GCM, PIN derivado, coordinación de pestañas, no caché de paneles | Durabilidad depende del navegador; falta aceptación completa de desconexión/reinicio/cuota en equipos reales |
| Integraciones | Fiscal unconfigured, pagos digitales manuales, WhatsApp click-to-chat | Sin proveedor fiscal, pasarela, webhooks ni envío automático |
| Recuperación | Backup streaming autenticado y restauración aislada probados | Sin WAL/PITR, copia externa, retención automatizada o RPO garantizado |

## Cobertura de revisión alineada con ASVS

Autorización/IDOR entre tiendas, limpieza de contexto SQL, manipulación de precios, MFA, referencias y replay, carga de fotos, exportación CSV y headers cuentan con pruebas automatizadas específicas. Esto es una matriz de seguimiento parcial, no una evaluación completa de todos los requisitos ASVS. Faltan revisión independiente, pruebas de abuso/carga, seguridad de despliegue, gestión/rotación de certificados reales, flujos de privacidad con conservación legal verificada y webhooks cuando se elijan proveedores.

## Antes de publicar

Preparar staging separado con proxy HTTPS, PHP-FPM no root, scheduler/worker supervisados, PostgreSQL privado y secretos diferentes por rol/entorno. Desactivar debug y configuración local, limitar trusted hosts/proxies y tamaño de solicitud. Verificar cookies Secure/HttpOnly/SameSite y errores sin trazas públicas. No usar artisan serve como servidor de producción. No exponer PostgreSQL ni copiar .runtime o archivos .env a la imagen/bundle.

Completar análisis de imágenes/contenedores, backup remoto y prueba de recuperación con volumen representativo. El workflow activo está en .github/workflows/tests.yml de la raíz. La publicación del 25/10 tuvo CI aprobado; las comprobaciones añadidas para esta entrega incluyen patrones de archivos privados/claves, navegador con datos sintéticos y construcción emparejada de imágenes. Registrar su ejecución remota para el nuevo commit. El detector por patrones es acotado y no reemplaza revisión de secretos. Las auditorías de dependencias limpias en una fecha no equivalen a ausencia de vulnerabilidades.


Verificación de dependencias frontend (24/10/2026): las cinco alertas npm reportadas se corrigieron con Vite+ 1.4.0 / Vitest 5.0.5 y vue-tsc 3.3.13 compatible. npm audit informa cero vulnerabilidades a esta fecha. Evidencia y límites en PROGRESS.md; no sustituye una revisión integral ni garantiza ausencia de alertas futuras.

Actualización del 24/10/2026: el rol catalog concede exclusivamente catálogo y fotos/importación/exportación de productos por empresa. El superadministrador puede asignarse esta ayuda de forma explícita por tienda, con contraseña reciente, MFA, cupo y auditoría. No recibe un rol owner automático. Los enlaces de invitación rotan al reenviar y se invalidan al revocar; aceptación y gestión se serializan por empresa. Las listas autenticadas no contienen token_hash ni el enlace secreto. El propietario puede retirar la membresía de ayuda. Correo real continúa desactivado en el entorno entregado.


Modo de una empresa por copia (25/10/2026): SingleBusiness bloquea las rutas de otras empresas y la consola de plataforma en modo single. La independencia física requiere instalar cada copia con sus propias bases/volúmenes, claves, cookies y archivos privados; cambiar solo BUSINESS_MODE no divide una base compartida. El preparador excluye configuraciones privadas y genera identidades de recursos diferentes. Los audios/transcripciones y herramientas locales no forman parte de las copias. Las limitaciones de producción y la emisión/correo real siguen vigentes.

Actualización 30/10/2026: las membresías cashier/warehouse pueden limitarse a una lista de almacenes; null mantiene todos, [] impide operar. Los cambios requieren propietario, MFA, contraseña reciente y caja cerrada/dispositivos conciliados. El servicio de inventario/pedidos/caja aplica el alcance y las listas filtran los datos. El pago mixto digital en POS exige propietario y verificación manual; el cajero no puede autoaprobarlo. Las sesiones se muestran con identificadores HMAC, sin exponer el ID de sesión real. La configuración de alojamiento separa secretos de sas_owner/sas_runtime y APP_KEY y usa un runtime www-data sin publicar PostgreSQL. Sigue pendiente validar TLS, SMTP, almacenamiento externo y hardening en el servidor contratado. Symfony Mime se actualizó de 8.1.7 a 8.1.10 por CVE-2026-45070; auditoría Composer posterior sin avisos, npm sin vulnerabilidades en las comprobaciones locales.
