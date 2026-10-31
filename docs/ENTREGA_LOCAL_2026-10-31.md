# Entrega local comercial — 31/10/2026

Entrega preparada para pago único por negocio, con el alcance de OFERTA_COMERCIAL.md. Programa local, una empresa por copia, cantidades enteras, notas de venta internas y pagos digitales manuales. No incluye dominio, hosting, correo real ni emisión fiscal.

## Paquete

`copias/entrega-local-comercial-20261031.zip` contiene código, PHP/PostgreSQL, dependencias, assets compilados, manual y acta sin firmar. Su hash está en el archivo `.sha256` contiguo. `VERSION_LOCAL.txt` identifica el commit local utilizado. El paquete no contiene cuentas, fotos privadas, bases, .env, contraseñas ni claves de respaldo del desarrollador. Extraer en una carpeta propia del cliente antes de instalar.

1. Abrir PowerShell en la carpeta extraída y ejecutar `armar-entorno-portatil.ps1`.
2. Ejecutar `iniciar.ps1` y completar `/instalacion` personalmente.
3. Configurar marca, empleados, productos y stock; capacitar al cliente y probar sus periféricos.
4. Configurar y ejecutar el respaldo diario con un destino separado; custodiar la clave y ensayar recuperación.
5. Registrar los resultados y observaciones en ACTA_ENTREGA_CONFORMIDAD.md. La prueba del desarrollador no firma la conformidad del cliente.

## Evidencia disponible

94 pruebas / 990 aserciones; cinco carreras; cuatro recorridos Playwright conjuntos, incluyendo subida/edición de foto y venta offline con recarga/sincronización; análisis estático y frontend aprobados. Instalación limpia independiente con 200 productos iniciales, reinicio y actualización sin regenerar claves. Respaldo cifrado, rechazo de clave incorrecta/corrupción, error secundario visible y ejecución real de una tarea temporal. Recuperación entre clústeres con fotos, saldos, contraseña, MFA y RLS comprobados. Auditorías Composer/npm sin avisos conocidos en esta revisión. Detalles y límites en PROGRESS.md.

La aceptación cubre la instalación local y el entorno de pruebas descritos. Periféricos físicos, volumen real del cliente, destino externo y capacitación se comprueban en cada entrega. No se declara producción pública, cumplimiento íntegro de los 24 prompts, disponibilidad garantizada ni certificación de seguridad. Las modificaciones de este cierre se guardaron en un commit local y no se publicaron en GitHub.
