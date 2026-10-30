# Manual Rápido del Operador — SAS SAN (Guía de Bolsillo)

Guía paso a paso para el uso diario del cajero y administrador de la tienda.

---

## 1. Inicio de Jornada: Apertura de Caja

1. Abre tu navegador e ingresa a: `http://localhost:8000` (o el acceso directo en el Escritorio).
2. Inicia sesión con tu correo y contraseña.
3. Ve a **Operaciones → Caja**.
4. Ingresa el **Fondo Inicial de Efectivo** (el sencillo con el que arranca la caja, ej. `S/ 100.00`).
5. Clic en **Abrir Caja**. ¡Listo para vender!

---

## 2. Ventas en el POS (Punto de Venta)

### Con Lector de Código de Barras
1. Con el cursor en el buscador de productos, apunta el lector láser a la etiqueta del producto.
2. El sistema lo detectará automáticamente y lo sumará a la cuenta de la venta.

### Búsqueda Manual
1. Escribe el nombre o código del producto (ej. `Polera` o `POL-01`).
2. Presiona `Enter` o haz clic sobre el resultado para agregarlo.

---

## 3. Tipos de Cobro y Vuelto

* **Cobro en Efectivo con vuelto:**
  * Si el total es `S/ 35.00` y el cliente paga con un billete de `S/ 50.00`:
  * En el campo *Efectivo recibido* escribe `50.00`.
  * El sistema te mostrará automáticamente en pantalla en color verde: **Vuelto: S/ 15.00**.
  * Clic en **Cobrar y Confirmar**.
* **Cobro con Yape / Plin / Transferencia:**
  * Solicita al cliente que te muestre la constancia en su celular.
  * Verifica el monto y el nombre de destino.
  * Ingresa los últimos 6 dígitos de la constancia/operación en el campo de referencia.
* **Pago Mixto (Parte efectivo, parte Yape):**
  * Ingresa cuánto dinero deja en efectivo y cuánto transfiere por Yape. El sistema validará que la suma sea exactamente el total de la compra.

---

## 4. Fin de Jornada: Arqueo y Cierre de Caja

Al finalizar el turno o cerrar la tienda:

1. Ve a **Operaciones → Caja**.
2. Cuenta físicamente todo el dinero en efectivo que tienes en el cajón (billetes y monedas).
3. Escribe ese monto en el campo **Efectivo Físico Contado**.
4. Haz clic en **Cerrar Turno de Caja**.
5. El sistema generará el resumen:
   * Total ventas en efectivo.
   * Total ventas digitales (Yape/banco).
   * Diferencia (Sobrante / Faltante si hubiera).
6. Imprime el reporte de cierre para tu control si lo necesitas.

---

## 5. Preguntas Frecuentes y Contingencias

### ¿Qué pasa si se corta la luz o se apaga la computadora?
1. Enciende la computadora nuevamente.
2. Abre el sistema: tu sesión de caja seguirá exactamente donde la dejaste.
3. El sistema no pierde ventas registradas ni cálculos de stock gracias a su base de datos transaccional.

### ¿Cómo sé si el respaldo automático de seguridad se hizo bien?
1. En la carpeta del sistema encontrarás un archivo llamado `ESTADO_ULTIMO_RESPALDO.txt`.
2. Ábrelo con doble clic. Si dice **Resultado: EXITOSO**, tus datos están 100% seguros y respaldados.
3. Si conectas una memoria USB, asegúrate de mantenerla conectada a la hora configurada (ej. 10:00 PM) para que reciba la copia externa.

### ¿Olvidé mi contraseña o perdí mi celular con el 2FA?
* Comunícate con el soporte técnico o ejecuta el asistente `recuperar-acceso-local.ps1` en la computadora principal con permisos de Administrador para asignar una clave nueva.
