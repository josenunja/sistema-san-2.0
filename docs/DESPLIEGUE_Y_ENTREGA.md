# Instalación, despliegue y entrega comercial

Cada negocio usa una instalación y recursos propios. La modalidad de cobro (instalación o mensualidad) no cambia esa separación. Este procedimiento prepara una entrega; registrar las pruebas efectuadas para cada cliente antes de darla por aceptada.

## Venta de instalación local

1. Preparar una carpeta nueva con preparar-copia.ps1 y un puerto libre. No entregar una carpeta que ya contenga .env, datos o claves de otro negocio.
2. En Windows con Docker sano, Node 22 y npm: ejecutar iniciar.ps1 -Modo Docker -Compilar dentro de la copia. Se crean app, PostgreSQL, scheduler y worker. El servidor se enlaza a 127.0.0.1; no habilita acceso público ni a otros equipos.
3. El propietario completa /instalacion con su contraseña y activa MFA. No hay cuenta universal. Cargar productos, stock y costos; probar una venta, su cobro y el arqueo.
4. Configurar el correo del negocio si se usarán invitaciones, verificación o recuperación. MAIL_MAILER=log registra mensajes locales y no equivale a entregar correos. Probar recepción con el propietario; no compartir logs completos con terceros.
5. Definir respaldos cifrados, ubicación externa y responsabilidad de conservación de claves. Los scripts nativos del proyecto requieren el entorno portátil documentado; no presuponer que estarán instalados en otro equipo.

Los límites independientes se configuran en BUSINESS_PRODUCT_LIMIT, BUSINESS_WAREHOUSE_LIMIT y BUSINESS_USER_LIMIT; valores iniciales 5000, 10 y 25. Se muestran en Mi tienda. Reiniciar los procesos después de cambiar configuración. No son recursos ilimitados ni una medida de capacidad. El modo platform mantiene sus planes separados.

## Preparar alojamiento (sin desplegar)

Requiere un servidor con Docker Compose, dominio propio y correo. El usuario todavía no ha contratado esos servicios. No utilizar direcciones de ejemplo como un despliegue real.

```powershell
.\preparar-produccion.ps1 -Nombre 'Mi negocio' -Identificador mi-negocio -Dominio tienda.midominio.pe -CorreoTLS operador@midominio.pe
```

El resultado privado está en .runtime/produccion/mi-negocio. Genera claves diferentes para APP_KEY, sas_owner y sas_runtime, cookie/proyecto únicos y un archivo runtime.env. Nunca sobrescribe una configuración anterior. Proteger esa carpeta y trasladarla por un canal privado al servidor; corregir en compose.env sus rutas absolutas para el destino. No subirla a GitHub ni incluirla en la copia comercial.

Completar el SMTP en runtime.env y conservar una copia cifrada de APP_KEY. Revisar el destinatario/from autorizado por el proveedor. La plantilla mantiene correo log hasta configurarlo: no inventa credenciales. Ajustar los límites a la capacidad contratada. Solo después de validar DNS, red y firewall ejecutar en el servidor, desde app/ (sustituir PRIVADO por la ruta de compose.env):

```sh
docker compose --env-file PRIVADO -f compose.production.yaml config --quiet
docker compose --env-file PRIVADO -f compose.production.yaml build
docker compose --env-file PRIVADO -f compose.production.yaml up -d db
docker compose --env-file PRIVADO -f compose.production.yaml --profile setup run --rm migrate
docker compose --env-file PRIVADO -f compose.production.yaml run --rm app php artisan business:install
docker compose --env-file PRIVADO -f compose.production.yaml up -d app scheduler worker web
docker compose --env-file PRIVADO -f compose.production.yaml exec app php artisan business:doctor --production
```

business:install solicita personalmente la contraseña del propietario. Su ejecución desde consola es un alta inicial confiada al operador; no se ejecuta automáticamente ni habilita registro público de personal. Después activar MFA. El doctor no muestra contraseñas ni envía correos y devuelve error si faltan condiciones comprobables; no certifica por sí solo la seguridad ni la entrega SMTP.

La imagen usa PHP-FPM, usuario www-data y build de frontend separado; no incluye node_modules, entorno privado ni herramientas Composer/Node en el runtime final. Caddy gestiona HTTPS cuando haya un dominio válido. Solo web publica 80/443; PostgreSQL no publica puertos. Scheduler y worker comparten almacenamiento privado. No montar el socket Docker. Las etiquetas de base deben fijarse por digest para cada entrega después de revisar/construir las imágenes; registrar esos digests y repetir análisis de vulnerabilidades antes del despliegue real.

## Actualización y recuperación

Guardar respaldo verificado antes de actualizar. Construir la versión nueva; ejecutar únicamente migraciones incrementales como sas_owner con el servicio migrate y recrear app/worker/scheduler/web. No regenerar APP_KEY y no ejecutar down -v. La reversión de código no implica revertir ni borrar migraciones con ventas existentes.

Para detener sin borrar: docker compose --env-file PRIVADO -f compose.production.yaml stop. Para revisar procesos/logs: sustituir stop por ps o logs --tail=100 app scheduler worker. Los logs pueden contener información privada; compartir únicamente fragmentos revisados.

La recuperación debe incluir PostgreSQL, storage privado, configuración y claves. El responsable configura un destino fuera del servidor, retención y cifrado autenticado, y ensaya una restauración en una base nueva. No se afirma que un backup guardado en el mismo disco cubra pérdida del servidor. No se han contratado almacenamiento externo ni WAL/PITR; medir RPO/RTO con el entorno real antes de ofrecer garantías.

## Aceptación por negocio

Verificar creación de cuenta/MFA, invitación recibida/revocada, publicación y edición de fotos, importación, stock, compra con recepción, POS efectivo/mixto autorizado, vuelto, crédito/abonos, caja/cierre/reapertura, devoluciones, tarifas/franjas, reserva vencida automáticamente y aislamiento frente a otra instalación. Probar correo de verificación y recuperación sin publicar los enlaces. Registrar versión, configuración, fecha y evidencia, sin contraseñas.

Aceptar offline con desconexión, reinicio, varias pestañas y recuperación de pendientes en los dispositivos del cliente. No ofrecerlo como infalible por pasar pruebas del dominio. El ticket sigue siendo interno. SUNAT y conciliación bancaria automática requieren proveedor y una entrega específica; no se incluyen como activadas en esta versión.
