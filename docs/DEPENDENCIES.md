# Dependencias y fuentes
- Starter oficial: https://github.com/laravel/vue-starter-kit (HEAD descargado el 17/10/2026; historial conservado en app/.git).
- Documentación Laravel 13: https://laravel.com/framework/docs/13.x/installation y https://laravel.com/framework/docs/13.x/starter-kits.
- PHP efectivo 8.4.26 en php:8.4-cli-bookworm. Dockerfile únicamente para desarrollo.
- PostgreSQL 18.6 seleccionado desde https://www.postgresql.org/support/versioning/.
- Laravel resuelto 13.37.0, Inertia Laravel 3.5.1, Fortify 1.41.0; versiones transitivas en composer.lock.
- Vite Plus fijado 0.3.3 tras auditoría; versiones frontend en package-lock.json.
- Node host 22.19.0, npm 10.9.3, Git 2.51.0, Docker Engine 29.8.2.

No se han auditado imágenes ni completado revisión de licencias. Redis pendiente de incorporación. Las etiquetas de imágenes de desarrollo aún no están fijadas por digest en Dockerfile; producción requiere fijación y revisión específica.

La compilación requirió alinear vite con npm:@voidzero-dev/vite-plus-core@0.3.3 y overrides; configuración tomada del README incluido en vite-plus. npm ci normal funciona con los lockfiles resultantes. El parche PostgreSQL 18.6 se comprobó mediante SHOW server_version.
