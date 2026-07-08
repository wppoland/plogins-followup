=== Plogins Followup - Follow-Up Emails for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, email, follow-up, post-purchase, review request
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Erfordert Plugins: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sende automatisierte E-Mails nach dem Kauf für WooCommerce: Dankes- und Bewertungsanfragen, eine festgelegte Anzahl von Tagen nach einer Bestellung.

== Description ==

Follow-up sendet automatisch E-Mails nach dem Kauf an deine WooCommerce-Kunden, eine konfigurierbare Anzahl von Tagen, nachdem eine Bestellung den Status „Abgeschlossen“ erreicht hat.

Zwei E-Mail-Typen sind sofort einsatzbereit:

* <strong>Dankeschön</strong>: eine kurze Nachricht kurz nach der Ausführung der Bestellung.
* <strong>Bewertungsanfrage</strong>: Bittet um eine Bewertung, sobald der Kunde das Produkt eine Weile besitzt.

Für jeden Typ lege fest, ob er aktiviert ist, welcher Bestellstatus ihn auslöst, wie viele Tage gewartet werden soll sowie den Betreff und den Text. Themen und Inhalt unterstützen „{customer}“ (Vorname), „{order}“ (Bestellnummer) und „{site}“ (Site-Name).

Ein tägliches wp-cron-Event nimmt fällige Bestellungen entgegen und versendet die E-Mails mit „wp_mail“, sodass sie das bereits vorhandene E-Mail-Setup der Website verwenden. Jede Nachverfolgung wird sofort nach dem Absenden für die Bestellung erfasst, so dass dieselbe nie zweimal gesendet wird, selbst wenn sich zwei Cron-Läufe überschneiden.

Entwickler können die Sequenz über den Filter „followup/sequence_steps“ erweitern. Jeder benutzerdefinierte Schritt kann seinen eigenen Auslöserstatus, seine eigene Verzögerung, seinen eigenen Betreff und seinen eigenen Text bereitstellen und dabei den idempotenten Planer von Followup wiederverwenden.

Das Plugin befindet sich noch nicht im WordPress.org-Verzeichnis. Quellcode und Issue-Tracker live unter https://github.com/wppoland/plogins-followup.

== Installation ==

1. Lade das Plugin nach „/wp-content/plugins/followup“ hoch oder installiere es über Plugins -> Neu hinzufügen.
2. Aktiviere es. WooCommerce muss aktiv sein.
3. Gehe zu WooCommerce -> Follow-ups, um E-Mail-Typen zu aktivieren und die Vorlagen zu bearbeiten.

== Frequently Asked Questions ==

= Documentation and links =

* <strong>Dokumentation</strong> - https://plogins.com/de/plogins-followup/docs/
* <strong>Plugin-Seite</strong> - https://plogins.com/de/plogins-followup/
* <strong>Quellcode</strong> – https://github.com/wppoland/plogins-followup
* <strong>Fehlerberichte und Funktionsanfragen</strong> – https://github.com/wppoland/plogins-followup/issues


= Does it require WooCommerce? =

Ja. WooCommerce muss installiert und aktiv sein.

= When are emails actually sent? =

Ein tägliches wp-cron-Ereignis sucht nach Bestellungen, die sich seit mindestens der konfigurierten Anzahl von Tagen im konfigurierten Status befinden, und sendet alle Bestellungen, die noch nicht gesendet wurden.

= Will a customer ever get the same email twice? =

Nein. Jeder Folgetyp wird für die Bestellung erfasst, sobald sie gesendet wurde, sodass sie für diese Bestellung nie erneut gesendet wird.

= Which placeholders can I use? =

„{customer}“ (Vorname), „{order}“ (Bestellnummer) und „{site}“ (Name deiner Website), sowohl im Betreff als auch im Text.

= Which order statuses can trigger a follow-up? =

Du wählen den Auslöserstatus pro E-Mail-Typ (z. B. „Verarbeitet“ oder „Abgeschlossen“) und die Verzögerung in Tagen vor dem Versand.


= Does this plugin work on WordPress Multisite? =

Ja. Dieses Plugin ist mit WordPress Multisite kompatibel. Aktiviere es im Netzwerk oder auf einzelnen Websites. Jede Site behält ihre eigenen Einstellungen und Daten.

== Screenshots ==

1. Der Bildschirm „Follow-ups-Einstellungen“: Aktiviere jeden E-Mail-Typ und bearbeite seinen Auslöserstatus, seine Verzögerung und seine Vorlagen.

== External Services ==

Followup stellt keine Verbindung zu externen Diensten her. Es verfügt über keine API-Schlüssel, sendet keine Daten an einen externen Standort und lädt nichts von einer Remote-URL oder einem CDN. Alles läuft auf deiner eigenen WordPress-Installation: Die Einstellungen werden in den Optionen „followup_settings“ und „followup_db_version“ gespeichert und jedes gesendete Follow-up wird als Auftragsmeta „_followup_sent_{type}“ aufgezeichnet, sodass es nie zweimal gesendet wird. E-Mails werden über das eigene „wp_mail()“ deiner Website mit dem Absender Ihres WooCommerce-Shops verschickt, sodass sie über das E-Mail-Setup weitergeleitet werden, das du bereits haben.

== Changelog ==

= 1.0.1 =
* Erste stabile Version.

= 0.1.5 =
* Für einen eindeutigeren Plugin-Namen in Plogins Followup für WooCommerce umbenannt.

= 0.1.4 =
* Der Filter „followup/email_links“ macht URLs verfügbar, die im letzten Follow-up-Text für die PRO-Engagement-Verfolgung entdeckt wurden.

= 0.1.3 =
* „Followup/should_send“-Filter, bevor ein Follow-up eine Bestellung beansprucht, sodass PRO den Versand auf eine ausgewählte Stunde oder einen Wochentag verschieben kann.

= 0.1.2 =
* Löse „followup/email_sent“ aus, nachdem ein Follow-up von wp_mail für PRO-Sendeberichte akzeptiert wurde.
* Dokumentiere den Platzhalter „{coupon}“ für Followup Pro-Gutscheinblöcke.

= 0.1.1 =
* Füge den Erweiterungsfilter „followup/sequence_steps“ hinzu, damit Add-ons benutzerdefinierte E-Mail-Schritte nach dem Kauf anhängen können.

= 0.1.0 =
* Erstveröffentlichung: Dankes- und Bewertungsanfrage-Folge-E-Mails mit Aktivierung pro Typ, Auslöserstatus, Verzögerung und Vorlagen; idempotenter täglicher Absender.
