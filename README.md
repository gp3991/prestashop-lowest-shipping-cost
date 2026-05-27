# Lowest Shipping Cost — środowisko uruchomieniowe

Moduł **PrestaShop 9** wyświetlający na karcie produktu **najniższy możliwy koszt
dostawy** (dopasowany do lokalizacji odwiedzającego, ilości i wariantu). To
repozytorium zawiera sam moduł (`modules/lowestshippingcost/`) oraz gotowe
środowisko Docker, żeby uruchomić go na czystej instalacji PrestaShop bez
ręcznej konfiguracji.

## Szybki start

```bash
# 1. Zależności modułu (autoloader PSR-4 — bez tego klasy z src/ nie zadziałają)
cd modules/lowestshippingcost && composer install --no-dev && cd -

# 2. PrestaShop — auto-instalacja (obraz prestashop/prestashop:9.1-apache + MariaDB)
docker compose up -d

# 3. Gdy instalacja się zakończy — włącz moduł (jako www-data, by nie psuć uprawnień)
docker compose exec -u www-data prestashop \
  php bin/console prestashop:module install lowestshippingcost
```

- **Sklep:** <http://localhost:8088>
- **Back office:** <http://localhost:8088/admin-dev> (`admin@example.com` / `prestashop123`)

Moduł jest podmontowany na żywo (bind-mount `./modules/lowestshippingcost`), więc
zmiany w kodzie są widoczne bez przebudowy obrazu.

## Dokumentacja modułu

Jak to działa, uwzględniane warunki, konfiguracja, model podatkowy, cache i testy
— zobacz [`modules/lowestshippingcost/README.md`](modules/lowestshippingcost/README.md).
