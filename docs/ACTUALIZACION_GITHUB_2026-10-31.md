# Actualización desde GitHub — 31/10/2026

Petición: revisar y traer los cambios de `josenunja/sistema-san-2.0`, sin publicar cambios locales ni activar servicios externos. La modalidad comercial elegida en la conversación es pago único por negocio, sin dominio, hosting ni emisión fiscal real incluidos.

## Código incorporado

- Rama `main`: avance lineal de `393baf5a489437b0b984878368b4cea51eeb40ea` a `84441a632687ca166ba1674b962dd4d5627ad58c`: ocho commits, 27 archivos añadidos/modificados, sin eliminaciones.
- La rama remota `hardening-finalizacion-2026` apunta al mismo commit que `main`. Las propuestas Dependabot descargadas como referencias no se integraron por separado.
- Antes de actualizar, los 453 archivos de la copia de publicación coincidían con los archivos de trabajo. Se respaldaron los archivos afectados en `.runtime/antes-actualizacion-2026-10-31T19-23-07-119Z/`, con manifiesto de revisiones y hashes.
- Incorporados: scripts de respaldo/restauración para clientes, programación de respaldos, recuperación de acceso por consola, ensamblado de entorno portátil, lanzador local, lectura de barras, manual de operador, acta de conformidad, prueba de jornada y tipos/correlativos de documentos.
- Al comparar los 468 archivos de la nueva revisión no hay ausentes ni diferencias de contenido; doce archivos difieren únicamente por terminadores de línea LF/CRLF. La copia Git `.runtime/publicacion-github` queda limpia y alineada con `origin/main`. El Git del starter dentro de `app/` no se usó para actualizar.

## Validación ejecutada

- TypeScript y compilación frontend: aprobados; 3497 módulos compilados.
- Formato/lint frontend: aprobados, 103 archivos de formato y 87 de lint.
- PHPStan: sin errores. Pint: aprobado.
- Migración incremental `000018` ejecutada primero como `sas_owner` en `sas_san_test` y después en `sas_san`. No se ejecutó `migrate:fresh`.
- `verificar.ps1`: 88 pruebas / 925 aserciones aprobadas como `sas_runtime`, exclusivamente en `sas_san_test`; incluye `JornadaCompletaTest`.
- Cinco carreras independientes aprobadas: última unidad, cupón, pago, devolución y offline.
- Análisis sintáctico de ocho scripts PowerShell: cero errores. Esto no acredita su ejecución funcional ni su aceptación en el equipo de un cliente.
- GitHub Actions de la revisión descargada: [ejecución 41129171402](https://github.com/josenunja/sistema-san-2.0/actions/runs/41129171402), trabajos `ci` y `delivery-images` aprobados. Incluye aceptación de navegador remota; no se repitió Playwright local en esta actualización.
- Inicio local ejecutado mediante `iniciar.ps1`. Inicio, `/login` y `/instalacion` respondieron HTTP 200; el inicio conduce a instalación porque aún no hay propietario configurado.

## Conservación de datos y límites

- Respaldo cifrado previo de la base de trabajo: `.runtime/backups/sas_san-20261031-171839-814342.ssbk`. No se restauró ni sobrescribió una base existente.
- Consulta previa a la migración: `sas_san` conserva cero usuarios y cero empresas. `sas_runtime` mantiene `rolsuper=false` y `rolbypassrls=false`.
- No se reemplazaron `.env`, `.env.native`, APP_KEY, almacenamiento ni claves privadas. No se crearon cuentas reales, ni se restablecieron contraseñas/MFA.
- **No ejecutar `app/scripts/crear-datos-prueba.php` sobre una instalación real:** el código recibido contiene borrado de usuarios/empresas y credenciales fijas. `activar-2fa.php` configura un secreto fijo. Ambos archivos se descargaron para conservar la revisión solicitada, pero no se ejecutaron; requieren revisión/corrección antes de usarse como herramientas de entrega.
- Los nuevos tipos de boleta/factura y correlativos no constituyen conexión con SUNAT ni evidencia de aceptación fiscal. La emisión real permanece sin proveedor y desactivada; esa funcionalidad no se incluye en la oferta elegida por el usuario.
- Los scripts nuevos de respaldo/restauración/recuperación no se ejecutaron sobre datos reales ni se programaron tareas. Su validación funcional y la de recuperación en otra instalación siguen pendientes; no confundir documentación recibida con aceptación ejecutada.
- No se publicó código ni se contrataron/configuraron dominio, hosting, SMTP, pasarela o almacenamiento externo. Este informe es documentación local de la actualización.
