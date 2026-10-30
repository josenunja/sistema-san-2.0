# Guía de Respaldos y Recuperación ante Desastres — SAS SAN

Esta guía describe el funcionamiento, automatización y procedimiento de recuperación de copias de seguridad para clientes de **SAS SAN**.

---

## 1. Características del Sistema de Respaldo

* **Cifrado de grado militar:** Las copias se empaquetan en archivos con extensión `.ssbk` cifrados mediante **Libsodium (XChaCha20-Poly1305)** con autenticación integrada. Si alguien roba o accede al archivo de respaldo, no puede leer los datos ni los clientes sin la clave `backup.key`.
* **Contenido completo en cada copia:**
  1. Base de datos completa (PostgreSQL) con todas las tablas, saldos, ventas, compras, caja y catálogo.
  2. Archivos privados (`storage/app/private`): comprobantes de pago adjuntos, documentos e imágenes privadas.
  3. Archivo de configuración (`config.env`).
* **Protección contra saturación de disco:** Rotación automática de 30 días (las copias con más de 30 días se eliminan de forma automática para no llenar la memoria del equipo).
* **Supervisión visible:** Se genera y actualiza un archivo `ESTADO_ULTIMO_RESPALDO.txt` en la raíz del sistema y en la carpeta de respaldos, con fecha, hora, tamaño y estado (`EXITOSO` o `ERROR`).

---

## 2. Automatización con Windows (Sin intervención diaria)

Para que el sistema se respalde automáticamente todos los días al cierre de turno (por ejemplo, a las 10:00 PM):

1. Abrir **PowerShell** en la carpeta del sistema.
2. Ejecutar:

```powershell
.\programar-respaldo.ps1 -Hora "22:00"
```

### Copia automática hacia memoria USB o Nube (Google Drive / OneDrive)
Si el cliente tiene conectada una memoria USB (por ejemplo en `E:\Respaldos_SAS`) o una carpeta de Google Drive sincronizada en su computadora:

```powershell
.\programar-respaldo.ps1 -Hora "22:00" -CopiaSecundaria "E:\Respaldos_SAS"
```

El script guardará la copia principal en la laptop y enviará una copia idéntica a la memoria USB o nube externa.

Para eliminar la tarea programada si fuera necesario:
```powershell
.\programar-respaldo.ps1 -Eliminar
```

---

## 3. Respaldo Manual Inmediato

Para realizar una copia en cualquier momento (por ejemplo, antes de actualizar el sistema o hacer un cambio mayor):

```powershell
.\respaldo-cliente.ps1
```

O enviándola directamente a una memoria externa:
```powershell
.\respaldo-cliente.ps1 -CopiaSecundaria "D:\Mi_USB\Respaldos"
```

---

## 4. Procedimiento de Recuperación ante Desastres (Laptop Rota / Robada)

Si la computadora principal se daña, se formatea o se pierde:

1. **Instalar SAS SAN en la nueva computadora** (siguiendo `docs/INSTALACION_REPOSITORIO.md`).
2. **Copiar el archivo de clave:** Colocar el archivo `backup.key` en la carpeta `claves/` de la nueva instalación. *(Regla de oro: Guarde siempre una copia de `backup.key` en su correo personal o en un pendrive guardado en un lugar seguro).*
3. **Ejecutar la restauración:**
   Abrir PowerShell y ejecutar indicando la ruta del archivo `.ssbk`:

   ```powershell
   .\restaurar-cliente.ps1 -ArchivoSsbk "E:\Respaldos_SAS\sas_san-20261030-220000-abc123.ssbk"
   ```

4. Confirmar la operación escribiendo `SI`.
5. El sistema descifrará la copia, restaurará la base de datos PostgreSQL, restablecerá las fotos y documentos privados, y dejará el sistema exactamente en el estado en que se encontraba al momento de esa copia.
