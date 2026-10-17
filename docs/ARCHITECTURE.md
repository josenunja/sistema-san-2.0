# Arquitectura
Monolito Laravel convencional. Fortify gestiona sesiones y MFA opcional del starter. ResolveTenant resuelve slug activo, verifica membresía de propietario en rutas privadas y establece app.tenant_id con set_config(..., true) dentro de una transacción. La transacción limpia el contexto al terminar.

Tenants es registro público mínimo; memberships es bootstrap privado consultado con usuario autenticado. Products tiene FORCE RLS y restricción SKU por empresa. El runtime no es dueño. La consola de provisión usa explícitamente el propietario.

Primera entrega conserva catálogo, sin carrito ni stock; publicar un producto no habilita compras. Las siguientes entregas incorporarán servicios por dominio sin crear módulos vacíos que aparenten funcionalidad.

Compose es solo desarrollo: PHP CLI y PostgreSQL en red interna, puerto HTTP solo loopback. Caché de archivos y cola database son provisionales hasta incorporar Redis con licencia/versiones verificadas.
