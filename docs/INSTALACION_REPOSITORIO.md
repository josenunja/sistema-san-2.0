# Instalación desde el repositorio

El repositorio contiene el código completo disponible, migraciones, pruebas, documentación y lockfiles. Las dependencias se instalan desde esos lockfiles. No contiene bases de datos, contraseñas, certificados, copias de seguridad, archivos privados ni el entorno portátil de un equipo particular. El alcance implementado y los pendientes están en PROGRESS.md.

## Windows con Docker Desktop

Requiere Git, Docker Desktop en buen estado y Node.js 22 con npm. Desde PowerShell:

```powershell
git clone https://github.com/josenunja/sistema-san-2.0.git
cd sistema-san-2.0
.\iniciar.ps1 -Modo Docker -Compilar
```

El script crea `.env` solo si no existe, genera una contraseña local aleatoria, instala dependencias, genera APP_KEY solo si está vacía, ejecuta migraciones incrementales y compila el frontend. Abrir http://localhost:8000/instalacion y crear personalmente la cuenta y su MFA. No existe contraseña universal. Es un entorno de desarrollo local, no un despliegue público.

Para detener los contenedores sin borrar datos:

```powershell
cd app
docker compose stop
```

No ejecutar `down -v` si se quieren conservar los datos. No regenerar APP_KEY en una instalación con datos cifrados.

## Instalación nativa alternativa

Se puede usar PHP 8.4 con Composer y extensiones pdo_pgsql, mbstring, intl, fileinfo, curl, openssl, gd, zip y sodium, junto con PostgreSQL 18.6 y Node.js 22. Crear una base de desarrollo y roles separados: propietario de migraciones y `sas_runtime` sin superusuario ni BYPASSRLS. Configurar un `.env` privado con host/puerto locales. Instalar con `composer install` y `npm ci` en `app/`, generar la clave una sola vez y migrar con el propietario. Proveer los permisos de runtime con `platform:runtime --env=local`; ejecutar la web con el runtime. Nunca usar SQLite para validar aislamiento.

El script nativo de este equipo presupone `.runtime/` y `.env.native` preparados, que se excluyen deliberadamente del repositorio. Sus scripts de backup y aceptación usan ese entorno; adaptar rutas y secretos al instalar en otro equipo.

## Verificación automatizada

La configuración GitHub Actions está en `.github/workflows/tests.yml`. Trabaja desde `app/`, provisiona PostgreSQL de pruebas y ejecuta análisis, compilación, integración RLS y cinco carreras con procesos independientes. No necesita secretos productivos ni transmite documentos fiscales. Las pruebas de negocio se ejecutan solamente en `sas_san_test`.

SUNAT sigue desactivada porque no hay proveedor contratado. Los pagos digitales son manuales y el correo local se registra en logs privados. Consultar SECURITY.md antes de conectar servicios o publicar la aplicación.
