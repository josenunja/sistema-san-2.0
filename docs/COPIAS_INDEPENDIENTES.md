# Una empresa por copia

La solicitud del 25/10/2026 es reutilizar el mismo programa para negocios diferentes y que cada instalación atienda únicamente a una empresa. La aplicación local y las copias nuevas usan `BUSINESS_MODE=single`. La portada abre el catálogo de esa empresa, el acceso administrativo abre su propia gestión y las rutas de otras empresas quedan bloqueadas. No se muestra un directorio público de tiendas.

## Preparar otra copia

Desde la carpeta principal, ejecutar PowerShell:

```powershell
.\preparar-copia.ps1 -Nombre 'Mi panadería' -Identificador mi-panaderia -Rubro bakery -Puerto 8012
```

Los datos que cambian son nombre, identificador, rubro y puerto. El resultado está en `copias/mi-panaderia`; sus instrucciones están en `EMPEZAR_AQUI.md`. El identificador usa minúsculas y guiones. Rubros: `fashion` (ropa), `bakery` (panadería), `mechanic` (mecánica/repuestos), `general`, `grocery`, `hardware`, `restaurant`, `services`, `footwear`, `accessories`, `mixed`, `other`.

La copia no incluye `.env`, datos, cuentas, contraseñas, `.runtime`, dependencias instaladas ni archivos privados del origen. Sí contiene el código y `.env.example` preparado. Cada ejecución genera un nombre de proyecto Docker y una cookie propios, incluso cuando se repite el identificador en destinos distintos.

## Instalar la copia

En un equipo con Docker Desktop sano, Node y npm, abrir PowerShell dentro de la nueva carpeta y ejecutar:

```powershell
.\iniciar.ps1 -Modo Docker -Compilar
```

Este inicio instala dependencias, crea PostgreSQL y volumen propios, genera una clave nueva si aún no existe y migra incrementalmente como `sas_owner`. La aplicación usa `sas_runtime`. Después abrir la dirección indicada, completar `/instalacion` con la cuenta y contraseña elegidas por el propietario y activar MFA en Seguridad. Nunca hay contraseña universal.

La copia preparada es código instalable, no un servidor ya desplegado. El PHP/PostgreSQL portátil privado de este equipo no se distribuye. Si se elige instalación nativa, se necesita un clúster PostgreSQL independiente con su propio puerto y credenciales; no apuntar a la base del negocio original.

Docker del equipo original presentó errores de disco y no se recuperó ni reseteó. Por eso su aplicación de trabajo continúa usando el entorno nativo, mientras las copias incluyen la alternativa Docker para equipos donde funcione correctamente.

## Uso por el propietario

Elegir el rubro, nombre y estilo; publicar los datos del negocio. En Catálogo, cargar nombre, precio y foto opcional. El código se genera automáticamente. Variantes y promociones están en Más opciones. Las existencias se registran desde Inventario con costo y referencia. Mi tienda / Mi equipo permite invitar personal de esa misma empresa.

Para panadería, mecánica y rubros generales se muestran catálogo, destacados, promociones y categorías propias, sin filtros de mujer/hombre/niños. No se implementaron recetas de producción, citas, órdenes de reparación ni fracciones de stock: el alcance sigue siendo productos y comercio con unidades enteras.

## Independencia de los datos

No duplicar una carpeta que ya contenga `.env` o datos y utilizarla como otra empresa. Usar siempre el preparador para una copia nueva. No compartir APP_KEY, contraseñas, volúmenes, archivos privados ni respaldos entre negocios. Un puerto diferente evita conflictos de ejecución; la separación de datos proviene de sus bases/volúmenes y claves independientes.

`BUSINESS_MODE=single` limita las rutas y vistas de esa aplicación; no extrae ni divide una base compartida existente. Si se pretende trasladar una empresa ya operativa, se necesita una migración revisada a su propia base. No se realizó tal traslado ni se borraron datos.

El modo anterior `BUSINESS_MODE=platform` se conserva por compatibilidad y pruebas; no es el modo de las copias preparadas. El correo real, fiscalidad, dominios, HTTPS, respaldos fuera del equipo y preparación productiva mantienen los pendientes descritos en SECURITY.md y PROGRESS.md.
