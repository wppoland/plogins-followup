=== Plogins Followup - Follow-Up Emails for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, email, follow-up, post-purchase, review request
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Wymaga wtyczek: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Wysyłaj automatyczne e-maile po zakupie dla WooCommerce: podziękowania i prośby o recenzję, po określonej liczbie dni od złożenia zamówienia.

== Description ==

Funkcja Followup wysyła automatyczne wiadomości e-mail po zakupie do klientów WooCommerce w konfigurowalną liczbę dni po osiągnięciu przez zamówienie statusu takiego jak Zrealizowane.

Gotowe do użycia są dwa typy e-maili:

* <strong>Dziękujemy</strong>: krótka notatka wkrótce po zrealizowaniu zamówienia.
* <strong>Prośba o recenzję</strong>: prosi o recenzję, gdy klient będzie miał produkt przez jakiś czas.

Dla każdego typu ustawiasz, czy jest on włączony, jaki status zamówienia go uruchamia, ile dni należy czekać oraz temat i treść. Podmioty i organy obsługują `{customer}` (imię), `{order}` (numer zamówienia) i `{site}` (nazwa witryny).

Codzienne zdarzenie wp-cron odbiera terminowe zamówienia i wysyła e-maile z adresem `wp_mail`, dzięki czemu korzystają z konfiguracji poczty, którą już ma witryna. Każde kolejne polecenie jest rejestrowane w powiązaniu z zamówieniem zaraz po jego wysłaniu, więc to samo nigdy nie jest wysyłane dwukrotnie, nawet jeśli dwa uruchomienia cron nakładają się na siebie.

Programiści mogą rozszerzyć sekwencję za pomocą filtra „kontynuacja/kroki_sekwencji”. Każdy niestandardowy krok może zapewnić własny status wyzwalacza, opóźnienie, temat i treść podczas ponownego wykorzystania idempotentnego harmonogramu Followup.

Wtyczki nie ma jeszcze w katalogu WordPress.org. Kod źródłowy i narzędzie do śledzenia problemów dostępne są na stronie https://github.com/wppoland/plogins-followup.

== Installation ==

1. Prześlij wtyczkę do `/wp-content/plugins/followup` lub zainstaluj poprzez Wtyczki -> Dodaj nową.
2. Aktywuj. WooCommerce musi być aktywny.
3. Przejdź do WooCommerce -> Kontynuacje, aby włączyć typy wiadomości e-mail i edytować szablony.

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

Nie. Każdy rodzaj dalszych działań jest rejestrowany w odniesieniu do zamówienia po jego wysłaniu, więc nigdy więcej nie jest wysyłany w ramach tego zamówienia.

= Which placeholders can I use? =

`{customer}` (imię), `{order}` (numer zamówienia) i `{site}` (nazwa Twojej witryny), zarówno w temacie, jak i w treści.

= Which order statuses can trigger a follow-up? =

Wybierasz status wyzwalacza według typu wiadomości e-mail (na przykład przetworzenie lub ukończenie) i opóźnienie w dniach przed wysłaniem.


= Does this plugin work on WordPress Multisite? =

Tak. Ta wtyczka jest kompatybilna z WordPress Multisite. Aktywuj go w sieci lub aktywuj na poszczególnych stronach; każda witryna przechowuje własne ustawienia i dane.

== Screenshots ==

1. Ekran ustawień dalszych działań: włącz każdy typ wiadomości e-mail i edytuj jego stan wyzwalania, opóźnienie i szablony.

== External Services ==

Followup nie łączy się z żadnymi usługami zewnętrznymi. Nie ma kluczy API, nie wysyła danych poza witrynę i nie ładuje niczego ze zdalnego adresu URL lub CDN. Wszystko działa na Twojej własnej instalacji WordPressa: ustawienia są przechowywane w opcjach `followup_settings` i `followup_db_version`, a każde wysłane polecenie uzupełniające jest rejestrowane jako meta zamówienia `_followup_sent_{type}`, dzięki czemu nigdy nie jest wysyłane dwukrotnie. Wiadomości e-mail są wysyłane za pośrednictwem funkcji `wp_mail()` znajdującej się w Twojej witrynie przy użyciu nadawcy ze sklepu WooCommerce, więc są przesyłane według dowolnej konfiguracji poczty, którą już posiadasz.

== Changelog ==

= 1.0.1 =
* Pierwsza stabilna wersja.

= 0.1.5 =
* Zmieniono nazwę na Plogins Followup dla WooCommerce, aby uzyskać bardziej charakterystyczną nazwę wtyczki.

= 0.1.4 =
* Filtr „followup/email_links” udostępnia adresy URL wykryte w końcowej treści dalszych działań w celu śledzenia zaangażowania PRO.

= 0.1.3 =
* Filtr „kontynuacja/powinna_wysłać” przed złożeniem zamówienia przez kolejną osobę, dzięki czemu PRO może odroczyć wysyłkę do wybranej godziny lub dnia tygodnia.

= 0.1.2 =
* Uruchom `followup/email_sent` po zaakceptowaniu monitu przez wp_mail w celu raportowania wysyłania PRO.
* Udokumentuj symbol zastępczy `{coupon}` dla bloków kuponów Followup Pro.

= 0.1.1 =
* Dodaj filtr rozszerzenia „followup/sequence_steps”, aby dodatki mogły dołączać niestandardowe kroki w wiadomościach e-mail po zakupie.

= 0.1.0 =
* Pierwsza wersja: dalsze wiadomości e-mail z podziękowaniami i prośbą o recenzję z możliwością włączenia poszczególnych typów, stanu wyzwalania, opóźnienia i szablonów; idempotentny nadawca dzienny.
