# Lowest Shipping Cost (PrestaShop 9)

Moduł wyświetla na karcie produktu **najniższy możliwy koszt dostawy** dla danego
produktu, dopasowany do **lokalizacji odwiedzającego**, **wybranej ilości** oraz
**wybranego wariantu (kombinacji)**, uwzględniając maksymalnie dużo warunków, jakie
w PrestaShop wpływają na cenę przesyłki.

## Jak to działa

Koszt wysyłki w PrestaShop nie jest atrybutem produktu — liczony jest na poziomie
koszyka i adresu w `Cart::getPackageShippingCost()` (`classes/Cart.php`). Na karcie
produktu nie ma ani koszyka, ani (dla gościa) jawnego adresu. Dlatego moduł
**odwzorowuje ten sam algorytm** dla wybranej ilości produktu i bierze
**minimum po dostępnych przewoźnikach** (z VAT):

> dla **destynacji** (kraj odwiedzającego / domyślny / wszystkie) × każdego
> dostępnego **przewoźnika** liczy koszt dla wybranej ilości i wariantu, biorąc
> najniższą wartość brutto.

Logika znajduje się w `src/Calculator/LowestShippingCostCalculator.php`.

**Reaktywność bez własnego JS:** blok jest częścią fragmentu
`product-additional-info` (w motywie *hummingbird* z PS 9 owinięty klasą
`js-product-additional-info`), który rdzeń (`ProductController::displayAjaxRefresh`)
re-renderuje przy każdej zmianie **ilości** lub **wariantu**. Hook odczytuje
`quantity_wanted` i rozwiązany `id_product_attribute`, więc cena aktualizuje się
na żywo. Kraj odwiedzającego pochodzi z `context->country` (geolokalizacja lub
adres zalogowanego klienta).

### Uwzględniane warunki

- **lokalizacja odwiedzającego** (geolokalizacja / adres zalogowanego klienta, fallback kraj domyślny),
- **ilość** z selektora (waga / wartość zamówienia / koszt dodatkowy × ilość),
- **wariant / kombinacja** (waga `Combination->weight` i cena `getPrice(idAttr)`),
- strefy / kraje i ceny przedziałów per strefa (tabela `delivery`),
- dostępność przewoźnika w strefie (`Carrier::checkCarrierZone`),
- metoda przewoźnika: wg wagi / wg ceny / darmowy (`getShippingMethod`),
- przedziały wagowe/cenowe + zachowanie poza zakresem (`range_behavior`),
- darmowy przewoźnik (`is_free`),
- globalne progi darmowej wysyłki (`PS_SHIPPING_FREE_PRICE`, `PS_SHIPPING_FREE_WEIGHT`),
- opłata manipulacyjna (`PS_SHIPPING_HANDLING` + `shipping_handling`),
- koszt dodatkowy produktu (`additional_shipping_cost`),
- VAT przewoźnika zależny od kraju (`getTaxesRate`),
- przewoźnicy przypisani do produktu (`product_carrier`),
- ograniczenia grup klientów (`carrier_group`),
- przeliczenie waluty (`Tools::convertPrice`),
- produkty wirtualne (brak wysyłki), multistore.

**Przewoźnicy zewnętrzni** (`shipping_external`) są świadomie pomijani — ich cenę
liczy własny moduł (`getPackageShippingCostFromModule`), często odpytując API
kuriera o adres dostawy, więc nie da się jej wiarygodnie odtworzyć na karcie
produktu (gdzie gość nie ma adresu). Jeśli dla danego produktu **żaden**
odtwarzalny przewoźnik nie da ceny, blok w ogóle się nie renderuje (zamiast
pokazywać komunikat „brak").

## Instalacja (środowisko developerskie)

Z katalogu nadrzędnego (gdzie jest `docker-compose.yml`):

```bash
# 1. Zależności + autoloader PSR-4 (generuje vendor/autoload.php).
#    Moduł ładuje go warunkowo; klasy z src/ nie zadziałają bez tego kroku.
cd modules/lowestshippingcost && composer install --no-dev && cd -

docker compose up -d
# poczekaj na auto-instalację PrestaShop, potem (jako www-data, by nie psuć
# uprawnień plików var/logs i var/cache):
docker compose exec -u www-data prestashop php bin/console prestashop:module install lowestshippingcost
```

> Polecenia `bin/console` uruchamiaj z `-u www-data`. Bez tego pliki (np.
> `var/logs/dev-*.log`) powstają jako root i Apache (www-data) nie może do nich
> pisać → błąd „could not be opened in append mode: Permission denied". Naprawa
> ad hoc: `docker compose exec prestashop chown -R www-data:www-data var/logs var/cache`.

Sklep: <http://localhost:8088> · Back office: <http://localhost:8088/admin-dev>
(`admin@example.com` / `prestashop123`). Moduł można też włączyć w BO → *Moduły*.

## Konfiguracja (BO → Moduły → Lowest Shipping Cost → Konfiguruj)

Strona ustawień to **kontroler Symfony + `FormType`** renderowany w Twigu (nie
HelperForm) — szczegóły w sekcji „Struktura i jakość kodu".

- **Destination** — względem jakiej destynacji liczyć minimum:
  - *Visitor location* (domyślnie) — kraj odwiedzającego (geolokalizacja / adres klienta, fallback kraj domyślny),
  - *Shop default country* — tylko kraj domyślny sklepu,
  - *Global minimum* — minimum po wszystkich aktywnych krajach.
- **Consider all customer groups** — minimum teoretyczne po wszystkich grupach
  (domyślnie: tylko grupa bieżącego odwiedzającego).
- **Skip external carriers** — pomijaj przewoźników zewnętrznych (domyślnie tak).
- **Show carrier and destination** — pokazuj nazwę przewoźnika i kraj.

> **Geolokalizacja:** prawdziwy per-visitor kraj dla gościa wymaga włączenia
> `PS_GEOLOCATION_ENABLED` + bazy GeoLite. Bez tego tryb *Visitor location* używa
> kraju domyślnego sklepu.

## Weryfikacja

1. Otwórz dowolny produkt demo → pod przyciskiem „Dodaj do koszyka" pojawia się
   blok „Delivery from …".
2. **Ilość**: zmień selektor ilości → cena przelicza się na żywo (waga / wartość /
   koszt dodatkowy × ilość); przekroczenie progu darmowej wysyłki → „Free".
3. **Wariant**: wybierz inną kombinację → koszt odzwierciedla wagę/cenę wariantu.
4. **Lokalizacja**: porównaj *Visitor location* (np. kraj z VAT) z *Global minimum*
   (najtańszy, często bezpodatkowy kraj); zaloguj klienta z adresem w innej strefie.
5. Ustaw `additional_shipping_cost` / `shipping_handling` → koszt rośnie o te składniki.
6. Przewoźnik z `range_behavior` = „dezaktywuj" i waga poza zakresem → znika z kandydatów.
7. Produkt wirtualny → komunikat o braku wysyłki.
8. Walidacja krzyżowa: dodaj wybraną ilość/wariant do koszyka, adres = kraj wynikowy,
   wybierz zwycięskiego przewoźnika — koszt z checkoutu = wartość z karty.

## Cache

Cache'owaniem zajmuje się dekorator `src/Calculator/CachedCalculator` (opakowuje
`LowestShippingCostCalculator`): wynik trafia do warstwy `Cache` PrestaShop pod kluczem
zależnym od produktu, **wariantu, ilości, destynacji**, waluty, sklepu i grupy. Zmiana
przewoźnika/produktu lub zapis konfiguracji **unieważnia cache** —
`CachedCalculator::invalidate()` / zapis ustawień bumpują wersję zaszytą w kluczu.

## Struktura i jakość kodu

Moduł trzyma się konwencji nowoczesnych modułów PrestaShop 9:

- **PSR-4 + Composer** — logika w `src/` pod namespace `PrestaShop\Module\LowestShippingCost`,
  autoloading przez `composer.json` (`type: prestashop-module`, `prepend-autoloader: false`),
  ładowany w głównym pliku (`vendor/autoload.php`). Brak ręcznych `require_once`.
  Główny plik modułu to **cienki adapter** (wpięcie hooków + delegacja); cała logika
  jest w `src/`: wycena (`Calculator/LowestShippingCostCalculator`), cache
  (`Calculator/CachedCalculator`), resolucja wejść (`Resolver/ProductPageResolver`),
  konfiguracja (`Configuration/ConfigurationData`).
- **Backend konfiguracji = Symfony + DI** — strona ustawień to kontroler Symfony
  (`src/Controller/Admin/ConfigurationController`, bazuje na `PrestaShopAdminController` —
  wzorzec PS 9, następca przestarzałego `FrameworkBundleAdminController`) + `FormType`
  (`src/Form/ConfigurationType`, `ChoiceType` + `SwitchType`) renderowany w Twigu
  (`views/templates/admin/`). W PS 9 kontroler musi być serwisem: rejestracja w
  `config/services.yml` z `autowire` + tagiem `controller.service_arguments` (zależność
  `ConfigurationData` wstrzykiwana przez konstruktor), trasa w `config/routes.yml`,
  `getContent()` przekierowuje na trasę. Dostęp pilnuje ukryty Tab admina
  (`$this->tabs`). **Front-office hook celowo tworzy kalkulator przez `new`** — w kontekście
  front-office `Module::get()` korzysta z lekkiego kontenera legacy, który nie zawiera serwisów
  modułu (serwisy są w kontenerze Symfony/admin).
- **`declare(strict_types=1)`** i typowane sygnatury w `src/` (`php >=8.1`, zgodnie z
  wymaganiem PrestaShop 9).
- **Nagłówek licencji AFL-3.0** w plikach PHP, `logo.png` dla listy modułów w BO.
- **Tłumaczenia** przez `$this->trans(..., 'Modules.Lowestshippingcost.Admin')` (PHP) oraz
  `{l s d='Modules.Lowestshippingcost.Shop'}` (szablony) — nowy system tłumaczeń.

```bash
composer install              # zależności dev + autoloader
composer test                 # PHPUnit (vendor/bin/phpunit)
vendor/bin/php-cs-fixer fix    # styl (@PSR12 + @Symfony, dostrojony do PS i PHP 7.2.5)
```

**Testy** (`tests/`): jednostkowe, bez bootowania PrestaShop — rdzeń wyceny
(`computeForCarrierZone`: metoda wagowa/cenowa, progi darmowej wysyłki, `range_behavior`,
opłata manipulacyjna, koszt dodatkowy, podatek + zaokrąglenie) jest testowany przez
test doubles core'a zdefiniowane w `tests/bootstrap.php`, plus niezmienność DTO `CalculationResult`.
