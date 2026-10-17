# Decisión 001: entorno local Docker
PHP y Composer no estaban instalados en el host; Docker Desktop sí estaba disponible. Se mantiene la arquitectura Laravel/Vue/PostgreSQL usando PHP en contenedor, sin instalar componentes globales. El starter descargado incluye Inertia 3 y Vite 8; se conservarán los lockfiles generados y las versiones efectivamente instaladas.
Fuente: https://laravel.com/framework/docs/13.x/starter-kits

Actualización del entorno: las dependencias PHP se almacenan en un volumen Docker porque la extracción en OneDrive se volvió muy lenta. Para herramientas PHP, use `docker compose run --rm app …`. Los archivos vendor parciales del host no se usan y no se han borrado automáticamente.
