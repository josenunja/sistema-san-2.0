# Respaldos y recuperación local

La entrega local completa incluye PHP, PostgreSQL y dependencias. Los comandos se ejecutan desde la carpeta instalada. Nunca compartir configuración, claves ni respaldos con otra empresa. Ninguna herramienta de esta guía borra la base activa para restaurarla.

## Crear y verificar copias

```powershell
.\respaldo-cliente.ps1 -Modo Nativo
.\respaldo-cliente.ps1 -Modo Nativo -CopiaSecundaria 'E:\RespaldosNegocio'
```

Se usa `app/.env.native` en modo nativo, aunque exista también `.env` de Docker. El volcado completo se realiza como `sas_owner`, con su contraseña privada; la web continúa como `sas_runtime`. El paquete `.ssbk` contiene base, configuración y archivos privados, con un manifiesto de tamaños/SHA-256 y cifrado autenticado. `backup.key` se conserva fuera del paquete, en `claves/` o en la ubicación privada existente de `.runtime/`.

`ESTADO_ULTIMO_RESPALDO.txt` contiene JSON con fecha UTC, `resultado`, `archivo`, `secundaria` y `detalle`. Un fallo al copiar al segundo destino produce `ERROR` y salida distinta de cero; la copia principal que ya se generó se conserva. La herramienta rechaza ejecuciones concurrentes en el mismo destino. No elimina respaldos antiguos: revise espacio y retención antes de retirar copias manualmente.

Una segunda carpeta del mismo disco sirve para probar la copia, pero no protege frente a pérdida de ese disco. Utilice un disco separado o un servicio de almacenamiento contratado y verifique su recuperación. Conserve una copia protegida de `backup.key` fuera del equipo, separada de los respaldos; perderla impide descifrarlos. APP_KEY está dentro de la configuración cifrada y es necesaria para recuperar campos cifrados de la aplicación.

## Programación diaria en Windows

```powershell
.\programar-respaldo.ps1 -Modo Nativo -Hora '22:00' -CopiaSecundaria 'E:\RespaldosNegocio'
```

Cada instalación recibe un nombre de tarea diferente. Usa la hora configurada en Windows, permisos del usuario actual y sesión interactiva: el equipo debe estar encendido, el usuario conectado y el destino disponible. Windows intenta ejecutar después una tarea omitida cuando vuelve a estar disponible. El resultado de la tarea y el archivo de estado deben revisarse; configurar una tarea no garantiza que se ejecutó.

```powershell
.\programar-respaldo.ps1 -Eliminar
```

Solo retira la tarea perteneciente a esta instalación. No borra archivos. Puede exportar la definición sin registrar una tarea con `-SoloXml`.

## Recuperar sin sobrescribir

En la instalación destino, primero preparar el entorno y colocar la clave original en una ubicación privada. Después:

```powershell
.\restaurar-cliente.ps1 -Modo Nativo -ArchivoSsbk 'E:\RespaldosNegocio\archivo.ssbk' -KeyPath 'E:\Claves\backup.key' -Puerto 8010
```

La herramienta autentica el cifrado y comprueba las rutas y todos los archivos del manifiesto antes de crear una base. Crea `sas_san_restore_<identificador>`, restaura con `--exit-on-error` y guarda los archivos y `config.env` en `.runtime/recuperaciones/<identificador>/`. La base, archivos y configuración activos se conservan. Si el proceso falla, no se anuncia éxito; una base parcial nueva se conserva para diagnóstico, sin sustituir la anterior.

Para revisar la recuperación:

```powershell
.\iniciar.ps1 -Configuracion 'RUTA_MOSTRADA\config.env'
```

La configuración recuperada usa las credenciales del runtime destino, conserva APP_KEY del respaldo, dirige el almacenamiento privado a la recuperación y fuerza correo por log. Compruebe acceso/MFA, productos y fotos, saldos, ventas, stock, caja y permisos. El soporte registra la aceptación y define qué configuración se usará en adelante; no copie a ciegas archivos de otra empresa sobre una instalación activa. Para respaldar la recuperación aceptada, pase la misma ruta con `-Configuracion` también al respaldo y a su tarea.

Los respaldos antiguos sin manifiesto v2 requieren las herramientas anteriores documentadas; no se aceptan silenciosamente como respaldos nuevos. El modo Docker requiere PHP local para cifrar/descifrar y la configuración local Compose documentada. La aceptación comercial de esta entrega se concentra en el modo nativo Windows.

Realizar la copia sin operaciones en curso mejora la coherencia entre el volcado transaccional de PostgreSQL y los archivos. No se ofrece WAL/PITR ni un tiempo garantizado de recuperación. Las ventas todavía pendientes en un navegador desconectado requieren su propia sincronización/recuperación; no están en el respaldo del servidor.
