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

Completar análisis de imágenes/contenedores, detección de secretos, backup remoto y prueba de recuperación con volumen representativo. La configuración CI de app/.github/workflows/tests.yml instala lockfiles, PostgreSQL 18.6, migra como owner y prueba como runtime; está preparada, todavía no ejecutada en GitHub. Las auditorías de dependencias limpias en una fecha no equivalen a ausencia de vulnerabilidades.
