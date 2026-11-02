# Administración general y propietarios de empresa

Actualizado el 01/11/2026. El código permite elegir entre una instalación independiente por negocio y una plataforma que gestiona varias empresas. La instalación principal del desarrollador se cambió al segundo modo por petición expresa del usuario; esta configuración privada y sus cuentas no se distribuyen en GitHub.

## Jerarquía de acceso

- Administrador general: crea empresas, invita a sus propietarios, activa/desactiva empresas, asigna planes y límites y gestiona invitaciones. Su cuenta tiene `platform_owner=true`.
- Propietario de empresa: configura diseño, productos, inventario, ventas, caja y equipo de su empresa mediante una membresía `owner`.
- Empleado: recibe Caja, Almacén o Solo catálogo y, cuando corresponde, un alcance de almacenes.
- Comprador: compra y consulta pedidos. Registrarse no concede permisos administrativos.

Ser administrador general no añade automáticamente una membresía comercial en todas las empresas. La ayuda de catálogo se concede explícitamente por tienda; el propietario conserva sus permisos. Un administrador puede también tener una membresía owner de su propia tienda, sin que eso amplíe su acceso comercial a otras empresas.

## Activar la plataforma en una instalación nueva

1. Instale el paquete Windows completo o prepare las dependencias desde el repositorio siguiendo `INSTALACION_REPOSITORIO.md`.
2. Complete la instalación inicial y cree personalmente la primera cuenta. El instalador inicial le concede propiedad de plataforma y de la primera tienda, sin contraseña universal.
3. Las instalaciones nuevas desde el 02/11/2026 ya arrancan en modo plataforma y no necesitan este paso. En una copia anterior en modo `single`, detenga los servicios de esa copia con `detener.ps1`. En su configuración privada `app/.env.native`, cambie únicamente `BUSINESS_MODE=single` por `BUSINESS_MODE=platform`. Conserve APP_KEY, credenciales, base y archivos privados. En un despliegue distinto use su configuración de entorno correspondiente.
4. Ejecute `iniciar.ps1`. No borre ni reinicie la base de datos.
5. Inicie sesión con la cuenta inicial. Si falta el segundo factor, se abre Seguridad y aparece la tarjeta **Administrador general**. Active y confirme MFA personalmente y guarde los códigos de recuperación.
6. Abra **Administrar empresas** o `/plataforma`. Las acciones sensibles exigen contraseña reciente.

Este cambio no transforma por sí solo un empleado existente en administrador. El permiso global se concede por un operador autorizado y se audita. No hay un formulario público para convertirse en administrador general. No se deben distribuir scripts de cuentas con contraseñas fijas.

En una copia independiente por cliente mantenga `BUSINESS_MODE=single`. Las rutas de plataforma quedan bloqueadas aunque la cuenta inicial conserve el atributo de instalación. Cambiar el modo no separa físicamente datos: cada copia independiente necesita su base, claves y archivos propios.

## Dar acceso a una nueva empresa

1. En **Empresas y planes**, complete nombre e identificador únicos, plan y correo del propietario.
2. Si prestará ayuda con productos, elija expresamente el permiso de catálogo para esa tienda.
3. Cree la empresa y entregue su invitación privada al destinatario correcto. El enlace vence a las 48 horas.
4. El propietario acepta, elige su contraseña y completa MFA. Después configura la tienda y carga sus productos y existencias.
5. El propietario invita a sus empleados desde **Mi tienda / Mi equipo** con el rol adecuado. Puede reenviar o revocar invitaciones y retirar accesos aceptados.

En el entorno local, el correo usa log: no se envían invitaciones reales. Un operador autorizado consulta únicamente el enlace solicitado en el registro privado y lo entrega por el canal acordado. No comparta el registro completo. El envío autónomo requiere conectar y probar un servicio de correo.

## Configurar la tienda del propio administrador

Si el administrador también es owner de una empresa, puede abrir `/gestion/IDENTIFICADOR/configurar` después de completar MFA. **Configurar diseño** aparece en la vista pública cuando tiene ese permiso. Los productos se crean desde Catálogo; sus unidades se reciben aparte en Inventario o Compras.

Al iniciar sesión, el administrador general entra primero a la consola de empresas; el propietario comercial sin ese permiso entra a su negocio. Las cuentas pendientes de MFA se guían a Seguridad. El panel general no elimina los controles de caja, inventario o autorización por empresa.

## Alcance de publicación

El repositorio y el paquete contienen programa, documentación y herramientas de instalación. No incluyen usuarios reales, contraseñas, secretos MFA, `.env.native`, bases ni respaldos. Crear una cuenta local no la crea en los equipos de quienes descargan el proyecto. Dominio, hosting, SMTP, pagos automáticos y emisión fiscal siguen requiriendo contratación, autorización y validación por separado.

## Exigencia de MFA configurable (instalaciones locales)

`REQUIRE_MFA` en `app/.env.native` controla si el personal debe activar la verificación en dos pasos para administrar. El valor por defecto es `true`. Con `REQUIRE_MFA=false` la gestión y la consola de plataforma no exigen MFA; las cuentas que ya lo activaron conservan su desafío al iniciar sesión. En `APP_ENV=production` el valor se ignora y MFA siempre es obligatorio. Reinicie con `detener.ps1` e `iniciar.ps1` tras cambiarlo. Para una cuenta que ya tiene MFA y no puede generar códigos, use `recuperar-acceso-local.ps1 -Email CORREO -Limpiar2FA`.
