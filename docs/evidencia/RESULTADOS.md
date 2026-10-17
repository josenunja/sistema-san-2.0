# Evidencia de validación local

Fecha: 17 de octubre de 2026, America/Lima.

- PostgreSQL 18.6, PHP 8.4.26, rol sas_runtime, base aislada sas_san_test.
- 36 pruebas aprobadas; 157 aserciones. Incluyen separación A/B, MFA, crédito y abonos, compras, promociones, CSV, imágenes, devolución, caducidad UTC, offline y contrato fiscal desactivado.
- Cinco carreras con procesos y conexiones independientes: última unidad, último uso de cupón, cobro repetido, devolución simultánea y replay offline. Efecto comercial único en cada escenario.
- PHPStan: cero errores. Formato PHP aplicado. Lint frontend sin advertencias, TypeScript aprobado, compilación 3454 módulos.
- Restauración cifrada: base nueva sas_san_restore_cb2afc6c00f4; 4,21 segundos sobre base pequeña de prueba. Coinciden 13 empresas, 23 productos, 11 pedidos, 39 movimientos, 2 ventas, 254 unidades físicas, 9 reservadas y saldos de caja/pagos de 300 céntimos. Un archivo privado verificado por hash. Lectura sin contexto RLS: cero filas.
- Navegador: directorio de dos negocios sintéticos, catálogo, carrito, total, pedido persistido y seguimiento. Una unidad menos disponible tras reservar. Acceso con contraseña y MFA de prueba. Captura tienda-demostracion.png sin credenciales.
- Servidor de trabajo: /up y /instalacion responden HTTP 200; instalación usa configuración nativa y Cache-Control no-store, private. Cuenta real pendiente de creación por su propietario.

Reproducir con verificar.ps1, probar-restauracion.ps1 y las comprobaciones descritas en README.md. Estos resultados no prueban cumplimiento completo ASVS, rendimiento productivo, recuperación ante pérdida de todo el host, conexión SUNAT real o aceptación offline completa en dispositivos reales. No hubo despliegue ni emisiones/pagos externos.
