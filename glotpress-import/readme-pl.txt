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

Wysyłaj automatyczne e-maile po zakupie dla WooCommerce: podziękowania i prośby o recenzję, po określonej liczbie dni od złożenia zamówienia.

== Description ==

Followup wysyła automatyczne wiadomości e-mail po zakupie do klientów WooCommerce w konfigurowalnej liczbie dni po osiągnięciu przez zamówienie statusu takiego jak Zrealizowane.

Gotowe do użycia są dwa typy e-maili:

* <strong>Dziękujemy</strong>: krótka notatka wkrótce po zrealizowaniu zamówienia.
* <strong>Prośba o recenzję</strong>: prosi o recenzję, gdy klient będzie miał produkt przez jakiś czas.

Dla każdego typu ustawiasz, czy jest on włączony, jaki status zamówienia go uruchamia, ile dni należy czekać oraz temat i treść. Tematy i treści obsługują `{customer}` (imię), `{order}` (numer zamówienia) i `{site}` (nazwa witryny).

Codzienne zdarzenie wp-cron odbiera zamówienia, których termin nadszedł, i wysyła e-maile za pomocą `wp_mail`, dzięki czemu korzystają z konfiguracji poczty, którą witryna już ma. Każdy follow-up jest rejestrowany przy zamówieniu zaraz po wysłaniu, więc ten sam nigdy nie zostaje wysłany dwukrotnie, nawet jeśli dwa uruchomienia crona nakładają się na siebie.

Programiści mogą rozszerzyć sekwencję za pomocą filtra `followup/sequence_steps`. Każdy niestandardowy krok może udostępnić własny status wyzwalacza, opóźnienie, temat i treść, wykorzystując ponownie idempotentny harmonogram Followup.

Wtyczki nie ma jeszcze w katalogu WordPress.org. Kod źródłowy i narzędzie do śledzenia problemów dostępne są na stronie https://github.com/wppoland/plogins-followup.

== Installation ==

1. Prześlij wtyczkę do `/wp-content/plugins/followup` lub zainstaluj poprzez Wtyczki -> Dodaj nową.
2. Aktywuj. WooCommerce musi być aktywny.
3. Przejdź do WooCommerce -> Follow-ups, aby włączyć typy wiadomości e-mail i edytować szablony.

== Frequently Asked Questions ==

= Documentation and links =

* <strong>Dokumentacja</strong> - https://plogins.com/pl/plogins-followup/docs/
* <strong>Strona wtyczki</strong> - https://plogins.com/pl/plogins-followup/
* <strong>Kod źródłowy</strong> - https://github.com/wppoland/plogins-followup
* <strong>Raporty o błędach i prośby o nowe funkcje</strong> - https://github.com/wppoland/plogins-followup/issues


= Does it require WooCommerce? =

Tak. WooCommerce musi być zainstalowany i aktywny.

= When are emails actually sent? =

Codzienne zdarzenie wp-cron sprawdza zamówienia, które mają skonfigurowany status przez co najmniej skonfigurowaną liczbę dni i wysyła te, które nie zostały jeszcze wysłane.

= Will a customer ever get the same email twice? =

Nie. Każdy typ follow-up jest rejestrowany przy zamówieniu po wysłaniu, więc nigdy nie jest wysyłany ponownie dla tego zamówienia.

= Which placeholders can I use? =

`{customer}` (imię), `{order}` (numer zamówienia) i `{site}` (nazwa Twojej witryny), zarówno w temacie, jak i w treści.

= Which order statuses can trigger a follow-up? =

Wybierasz status wyzwalacza dla każdego typu wiadomości e-mail (na przykład w trakcie realizacji lub zrealizowane) oraz opóźnienie w dniach przed wysłaniem.


= Does this plugin work on WordPress Multisite? =

Tak. Ta wtyczka jest kompatybilna z WordPress Multisite. Włącz ją dla całej sieci lub na poszczególnych stronach; każda witryna przechowuje własne ustawienia i dane.

== Screenshots ==

1. Ekran ustawień Follow-ups: włącz każdy typ wiadomości e-mail i edytuj jego status wyzwalacza, opóźnienie i szablony.

== External Services ==

Followup nie łączy się z żadnymi usługami zewnętrznymi. Nie ma kluczy API, nie wysyła danych poza witrynę i nie ładuje niczego ze zdalnego adresu URL ani z CDN. Wszystko działa w Twojej własnej instalacji WordPressa: ustawienia są przechowywane w opcjach `followup_settings` i `followup_db_version`, a każdy wysłany follow-up jest rejestrowany jako meta zamówienia `_followup_sent_{type}`, dzięki czemu nigdy nie jest wysyłany dwukrotnie. Wiadomości e-mail są wysyłane za pomocą funkcji `wp_mail()` Twojej witryny, z użyciem nadawcy Twojego sklepu WooCommerce, więc korzystają z dowolnej konfiguracji poczty, którą już masz.

== Translations ==

Plogins Followup zawiera tłumaczenia interfejsu wtyczki na język polski, niemiecki i hiszpański. Domena tekstowa to `plogins-followup`, więc pakiety językowe WordPress.org mogą również zastąpić lub rozszerzyć te dołączone tłumaczenia.

== Changelog ==

= 1.0.2 =
* Dodano dołączone tłumaczenia na język polski, niemiecki i hiszpański dla interfejsu wtyczki.

= 1.0.1 =
* Pierwsza stabilna wersja.

= 0.1.5 =
* Zmieniono nazwę na Plogins Followup for WooCommerce, aby nadać wtyczce bardziej charakterystyczną nazwę.

= 0.1.4 =
* Filtr `followup/email_links` udostępnia adresy URL wykryte w końcowej treści follow-upa na potrzeby śledzenia zaangażowania w PRO.

= 0.1.3 =
* Filtr `followup/should_send` uruchamiany, zanim follow-up przejmie zamówienie, dzięki czemu PRO może odroczyć wysyłkę do wybranej godziny lub dnia tygodnia.

= 0.1.2 =
* Uruchamia `followup/email_sent` po zaakceptowaniu follow-upa przez wp_mail na potrzeby raportowania wysyłek w PRO.
* Udokumentowano symbol zastępczy `{coupon}` dla bloków kuponów Followup Pro.

= 0.1.1 =
* Dodano filtr rozszerzeń `followup/sequence_steps`, aby dodatki mogły dołączać niestandardowe kroki e-maili po zakupie.

= 0.1.0 =
* Pierwsza wersja: e-maile follow-up z podziękowaniem i prośbą o recenzję, z włączaniem poszczególnych typów, statusem wyzwalacza, opóźnieniem i szablonami; idempotentny dzienny mechanizm wysyłki.
