# SAS SAN

Aplicación local multiempresa en español para catálogo, inventario, compras, pedidos, POS, caja y crédito. Laravel 13, Vue 3 y PostgreSQL 18.6. La especificación mejorada está en docs/ESPECIFICACION_MEJORADA.md; el alcance y sus límites están en docs/PROGRESS.md.

## Abrir en este equipo

Si acabas de clonar el repositorio en otro equipo, consulta primero [Instalación desde GitHub](docs/INSTALACION_REPOSITORIO.md). El entorno portátil privado y las bases de datos locales no se distribuyen en Git.

En PowerShell, desde esta carpeta:

```powershell
.\iniciar.ps1
```

Abrir http://localhost:8000. La instalación inicial permite crear tu cuenta y primera empresa. Introduce tu propia contraseña y configura el segundo factor en Seguridad. No hay una contraseña universal. La instalación se cierra después de crear el primer usuario. Los compradores pueden crear cuentas separadas por tienda; eso no concede acceso al personal.

El entorno portátil ya preparado está en `.runtime/`: PHP 8.4.26 y PostgreSQL 18.6. PostgreSQL escucha únicamente en 127.0.0.1:55432; la aplicación en 127.0.0.1:8000. No se necesita recuperar Docker para usar este entorno. El inicio ejecuta migraciones incrementales, preserva APP_KEY y arranca el vencimiento programado de reservas. Para recompilar cambios visuales: `.\iniciar.ps1 -Compilar`.

Docker Desktop presentó errores de entrada/salida en su disco virtual incluso tras el reinicio autorizado. No se reinicializó ni borró ese disco. La base nativa es independiente: no se afirma haber recuperado datos anteriores de Docker. El modo alternativo `.\iniciar.ps1 -Modo Docker` solo debe usarse cuando Docker vuelva a estar sano; sus datos son distintos de los nativos.

## Recorrido de uso

1. Crear productos, variantes e imágenes en Catálogo. Cada SKU representa una variante. Se admite importación CSV con vista previa antes de confirmar.
2. Crear almacenes y recibir mercadería con costo, proveedor y referencia. En Compras se guardan borradores, confirmaciones y recepciones parciales por variante.
3. Abrir caja para vender desde POS. El crédito requiere cliente identificado y límite autorizado por el propietario.
4. Configurar y publicar la tienda, tarifa de entrega e instrucciones de pago. Los medios sin configurar permanecen desactivados.
5. Compartir la dirección pública de la tienda. Checkout guarda el pedido y reserva unidades durante 30 minutos. Las constancias digitales requieren revisión; no confirman un pago automáticamente.
6. Gestionar preparación, entrega, abonos, devoluciones y reembolsos desde Pedidos y ventas. Nunca se borran ventas confirmadas.

Los correos locales de verificación/invitación/recuperación se registran en `app/storage/logs/laravel.log`: no se están enviando correos reales. Ese archivo es privado. WhatsApp es un enlace que el comprador decide abrir y enviar. No hay pagos automáticos ni emisión SUNAT activa. Los tickets están identificados como documentos internos.

## Verificación y recuperación

```powershell
.\verificar.ps1
.\respaldo.ps1
.\probar-restauracion.ps1
```

Las pruebas usan exclusivamente `sas_san_test` con rol SQL restringido. La restauración crea una base nueva y nunca sobrescribe `sas_san`. Los respaldos cifrados `.ssbk` se guardan en `.runtime/backups/`; su clave separada está en `.runtime/backup.key`. Conserva una copia segura de esa clave y de APP_KEY fuera del equipo: perderlas impide recuperar los datos cifrados. El respaldo local no proporciona recuperación ante pérdida de todo el disco.

Para revisar datos ficticios en una base separada: `.\aceptar-en-navegador.ps1` abre el servidor de pruebas en http://localhost:8002. Sus credenciales aleatorias están en el archivo privado `.runtime/demo-access.json`. No mezclar esa demostración con la empresa real.

En `app/`: `npm run check`, `npm run types:check`, `npm run build`. PHP: `..\.runtime\php\php.exe vendor/bin/phpstan analyse --memory-limit=512M` y `..\.runtime\php\php.exe vendor/bin/pint --test`.

## Límites de entrega

Este servidor es local, no un despliegue público. Antes de producción faltan validación de infraestructura/TLS, secretos separados, correo real, copias fuera del host, pruebas de carga y aceptación completa en dispositivos offline. La facturación real depende del proveedor que todavía no has contratado. Consulte docs/PROGRESS.md y docs/SECURITY.md: no se declara cumplimiento completo de los 24 pasos ni certificación de seguridad.
