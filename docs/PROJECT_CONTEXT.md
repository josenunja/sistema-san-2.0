# Contexto del proyecto

SAS SAN: aplicación local Laravel 13 / Vue 3 / Inertia 3 / PostgreSQL 18.6. Español, PEN en céntimos, instantes UTC, presentación y cortes America/Lima. Fuente original conservada en REQUISITOS_ORIGINALES.md; revisión en ESPECIFICACION_MEJORADA.md. Consultar PROGRESS.md para evidencia y límites, OPERACION.md para uso.

La petición vigente es continuar y completar el proyecto. El usuario no tiene proveedor fiscal; emisión real desactivada. Autorizó reiniciar Docker Desktop; el reinicio no resolvió errores de disco I/O. No se reseteó ni borró Docker. Se preparó PHP/PostgreSQL portátil independiente en .runtime y .env.native. No confundir esas bases con volúmenes Docker ni afirmar recuperación de datos originales.

Inicio: iniciar.ps1. Base de trabajo sas_san, pruebas sas_san_test. Migraciones incrementales como sas_owner; web/pruebas de negocio como sas_runtime. Nunca migrate:fresh fuera de sas_san_test. El primer usuario real aún debe completar instalación y MFA; no inventar sus credenciales. Pruebas y demostración usan datos sintéticos aislados, servidor 8002 y credenciales aleatorias privadas. APP_KEY no se regenera al actualizar.

El producto incluye catálogo/CSV/imágenes/variantes, inventario/transferencias/conteos/costos, compras por variante/recepciones/reversos, tienda/pedidos/seguimiento, POS/caja/crédito/abonos, devoluciones/reembolsos, clientes, cupones, configuración versionada, invitaciones/roles, plataforma/planes manuales, offline con asignación exclusiva y recuperación, respaldos cifrados y contratos fiscales desactivados. Hay límites funcionales y productivos pendientes registrados; no afirmar que las 24 etapas están completas sin evidencia.

## Repositorio publicado

Código y documentación publicados a petición del usuario en https://github.com/josenunja/sistema-san-2.0, rama main. No incluye .env, .env.native, .runtime, bases, respaldos, claves ni dependencias instaladas. La copia de publicación con la estructura raíz completa está en .runtime/publicacion-github. El repositorio Git original dentro de app/ sigue apuntando al starter de Laravel: no usar ese remoto para publicar el proyecto. Sincronizar las fuentes revisadas con la copia de publicación antes de nuevos commits. Las instrucciones para clonar están en docs/INSTALACION_REPOSITORIO.md. Publicar código en GitHub no despliega ni activa integraciones.
