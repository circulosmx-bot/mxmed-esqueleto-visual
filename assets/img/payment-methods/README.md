# Payment method brand assets

Estos archivos son assets candidatos entregados por el equipo de producto para una futura pantalla de Pago seguro en suscripciones.

Actualmente no estan conectados al runtime y no deben aparecer automaticamente en la UI. Su existencia en esta carpeta no significa que el metodo de pago este habilitado, disponible o contratado.

Antes de uso productivo debe confirmarse la procedencia, licencia y autorizacion de cada marca. Stripe Payment Element debe seguir siendo la fuente preferida para mostrar logos y metodos de pago dentro del formulario real.

Reglas de uso futuro:

- No presentar OXXO ni SPEI como mensualidad automatica.
- Apple Pay y Google Pay dependen de elegibilidad real del dispositivo, navegador, cuenta y configuracion Stripe.
- La disponibilidad de cualquier metodo debe venir del contrato real y de Stripe, no de la presencia del archivo.
- No incluir secretos ni configuracion Stripe en esta carpeta.
- No referenciar estos assets desde HTML, JS o CSS hasta una microfase especifica de Pago seguro.
