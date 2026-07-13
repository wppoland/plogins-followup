=== Plogins Followup - Follow-Up Emails for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, email, follow-up, post-purchase, review request
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Envía correos electrónicos automatizados posteriores a la compra para WooCommerce: solicitudes de agradecimiento y revisión, una cantidad determinada de días después de realizar un pedido.

== Description ==

Followup envía correos electrónicos automatizados posteriores a la compra a tus clientes de WooCommerce, una cantidad configurable de días después de que un pedido alcanza un estado como Completado.

Dos tipos de correo electrónico vienen listos para usar:

* <strong>Gracias</strong>: una breve nota poco después de que se complete el pedido.
* <strong>Solicitud de revisión</strong>: solicita una revisión una vez que el cliente ha tenido el producto por un tiempo.

Para cada tipo, estableces si está habilitado, qué estado del pedido lo activa, cuántos días esperar y el asunto y el cuerpo. Los asuntos y los cuerpos admiten `{customer}` (nombre), `{order}` (número de pedido) y `{site}` (nombre del sitio).

Un evento diario de wp-cron recoge los pedidos vencidos y envía los correos electrónicos con `wp_mail`, por lo que utilizan cualquier configuración de correo que ya tenga el sitio. Cada seguimiento se registra en el pedido tan pronto como se envía, por lo que el mismo nunca se envía dos veces, incluso si dos ejecuciones de cron se superponen.

Los desarrolladores pueden ampliar la secuencia a través del filtro `followup/sequence_steps`. Cada paso personalizado puede proporcionar su propio estado de activación, retraso, asunto y cuerpo mientras reutiliza el programador idempotente de Followup.

El complemento aún no está en el directorio de WordPress.org. El código fuente y el gestor de incidencias están en https://github.com/wppoland/plogins-followup.

== Installation ==

1. Sube el complemento a `/wp-content/plugins/followup`, o instálalo desde Complementos -> Añadir nuevo.
2. Actívalo. WooCommerce debe estar activo.
3. Ve a WooCommerce -> Follow-ups para activar los tipos de correo electrónico y editar las plantillas.

== Frequently Asked Questions ==

= Documentation and links =

* <strong>Documentación</strong> - https://plogins.com/es/plogins-followup/docs/
* <strong>Página de complementos</strong> - https://plogins.com/es/plogins-followup/
* <strong>Código fuente</strong> - https://github.com/wppoland/plogins-followup
* <strong>Informes de errores y solicitudes de funciones</strong> - https://github.com/wppoland/plogins-followup/issues


= Does it require WooCommerce? =

Sí. WooCommerce debe estar instalado y activo.

= When are emails actually sent? =

Un evento diario de wp-cron verifica los pedidos que han estado en el estado configurado durante al menos la cantidad de días configurados y envía los que aún no se han enviado.

= Will a customer ever get the same email twice? =

No. Cada tipo de seguimiento se registra en el pedido una vez que se envía, por lo que nunca se vuelve a enviar para ese pedido.

= Which placeholders can I use? =

`{customer}` (nombre), `{order}` (número de pedido) y `{site}` (nombre de tu sitio), tanto en el asunto como en el cuerpo.

= Which order statuses can trigger a follow-up? =

Eliges el estado de activación por tipo de correo electrónico (por ejemplo, procesado o completado) y la demora en días antes de que se envíe.


= Does this plugin work on WordPress Multisite? =

Sí. Este complemento es compatible con WordPress Multisite. Actívalo en red o en sitios concretos; cada sitio mantiene sus propios ajustes y datos.

== Screenshots ==

1. La pantalla de ajustes de Follow-ups: activa cada tipo de correo electrónico y edita su estado de activación, retraso y plantillas.

== External Services ==

Followup no se conecta a ningún servicio externo. No tiene claves de API, no envía datos fuera del sitio y no carga nada desde una URL o CDN remota. Todo se ejecuta en tu propia instalación de WordPress: los ajustes se almacenan en las opciones `followup_settings` y `followup_db_version`, y cada seguimiento enviado se registra como meta de pedido `_followup_sent_{type}`, por lo que nunca se envía dos veces. Los correos electrónicos se envían a través del `wp_mail()` de tu sitio utilizando el remitente de tu tienda WooCommerce, por lo que viajan mediante cualquier configuración de correo que ya tengas.

== Translations ==

Plogins Followup incluye traducciones al polaco, alemán y español para la interfaz del complemento. El dominio de texto es `plogins-followup`, por lo que los paquetes de idioma de WordPress.org también pueden anular o ampliar estas traducciones empaquetadas.

== Changelog ==

= 1.0.2 =
* Se añadieron traducciones integradas en polaco, alemán y español para la interfaz del complemento.

= 1.0.1 =
* Primera versión estable.

= 0.1.5 =
* Renombrado a Plogins Followup for WooCommerce para un nombre de complemento más distintivo.

= 0.1.4 =
* El filtro `followup/email_links` expone las URL descubiertas en el cuerpo de seguimiento final para el seguimiento de la participación PRO.

= 0.1.3 =
* Filtro `followup/should_send` antes de que un seguimiento reclame un pedido, por lo que PRO puede diferir los envíos a una hora o día de la semana elegidos.

= 0.1.2 =
* Activa `followup/email_sent` después de que wp_mail acepte un seguimiento para informes de envío PRO.
* Documenta el marcador de posición `{coupon}` para los bloques de cupones de Followup Pro.

= 0.1.1 =
* Añade el filtro de extensión `followup/sequence_steps` para que los complementos puedan añadir pasos de correo electrónico personalizados posteriores a la compra.

= 0.1.0 =
* Lanzamiento inicial: correos electrónicos de seguimiento de solicitud de revisión y agradecimiento con habilitación por tipo, estado de activación, retraso y plantillas; emisor diario idempotente.
