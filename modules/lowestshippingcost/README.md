# Lowest Shipping Cost (PrestaShop 9)

The module shows the **lowest possible delivery cost** for a given product — on the
**product page** and inside the **Quick View** modal — matched to the **visitor's
location**, the **selected quantity** and the **selected variant (combination)**,
accounting for as many of the conditions that affect shipping price in PrestaShop as
possible.

## How it works

Shipping cost in PrestaShop is not a product attribute — it is computed at the
cart and address level in `Cart::getPackageShippingCost()` (`classes/Cart.php`).
The product page has neither a cart nor (for a guest) an explicit address, so the
module **mirrors that same algorithm** for the selected quantity and takes the
**minimum across the available carriers** (tax included):

> for the **destination** (visitor's country / default / all) × each available
> **carrier** it computes the cost for the selected quantity and variant, keeping
> the lowest gross value.

The logic lives in `src/Calculator/LowestShippingCostCalculator.php`.

**One hook, product page + Quick View:** the block is rendered via the
`displayProductPriceBlock` hook in the price area (`product-prices.tpl`). That partial
is included by both the full product page **and** hummingbird's `quickview.tpl`, so a
single registration covers both — unlike `displayProductAdditionalInfo`, which
hummingbird's quickview does not render. The hook fires once per price-block *type*;
the module renders only at the block-level `weight` slot of the product sheet (valid
markup, and absent from listing miniatures, which use other types — so the block never
shows up on product tiles).

**Reactivity without custom JS:** the core (`ProductController::displayAjaxRefresh`)
replaces the whole `product_prices` node on every **quantity** or **variant** change,
so the price updates live. (The neighbouring `product_add_to_cart` fragment is *not*
swapped wholesale — the core only patches the button / availability / minimal-quantity
inside it — which is why a price-area hook, not an add-to-cart one, stays reactive.)
The hook reads `quantity_wanted` and the resolved `id_product_attribute`; the visitor's
country comes from `context->country` (geolocation or the logged-in customer's address).

### Conditions taken into account

- **visitor location** (geolocation / logged-in customer address, fallback to the default country),
- **quantity** from the selector (weight / order total / additional cost × quantity),
- **variant / combination** (weight `Combination->weight` and price `getPrice(idAttr)`),
- zones / countries and per-zone range prices (`delivery` table),
- carrier availability per zone (`Carrier::checkCarrierZone`),
- carrier method: by weight / by price / free (`getShippingMethod`),
- weight/price ranges + out-of-range behavior (`range_behavior`),
- free carrier (`is_free`),
- global free-shipping thresholds (`PS_SHIPPING_FREE_PRICE`, `PS_SHIPPING_FREE_WEIGHT`),
- handling fee (`PS_SHIPPING_HANDLING` + `shipping_handling`),
- product additional cost (`additional_shipping_cost`),
- carrier VAT per country (`getTaxesRate`) and the **price display mode**:
  net/gross according to the customer group (`getTaxCalculationMethod` — consistent
  with the product price, e.g. B2B net), the global `PS_TAX` switch and
  `PS_ATCP_SHIPWRAP` (Germany) — logic in `Calculator/TaxContext`,
- carriers assigned to the product (`product_carrier`),
- customer group restrictions (`carrier_group`),
- currency conversion (`Tools::convertPrice`),
- virtual products (no shipping), multistore.

**External carriers** (`shipping_external`) are skipped on purpose — their price is
computed by their own module (`getPackageShippingCostFromModule`), often by querying
the courier's API with the delivery address, so it cannot be reliably reproduced on
the product page (where a guest has no address). If **no** reproducible carrier
yields a price for a product, the block is not rendered at all (instead of showing a
"not available" message).

## Installation (development environment)

From the parent directory (where `docker-compose.yml` lives):

```bash
docker compose up -d
# wait for PrestaShop's auto-install, then (as www-data, to avoid breaking the
# permissions of var/logs and var/cache):
docker compose exec -u www-data prestashop php bin/console prestashop:module install lowestshippingcost
```

The production autoloader is committed (`vendor/`, generated with `composer install
--no-dev`), so no Composer step is required to run the module. PrestaShop loads a
module's `src/` classes **only** via `vendor/autoload.php`
(`AppKernel::enableComposerAutoloaderOnModules`), so without it the Symfony admin
service would not resolve ("Class … cannot be found" on install). Run `composer
install` only for development (the dev tools below).

> Run `bin/console` commands with `-u www-data`. Otherwise files (e.g.
> `var/logs/dev-*.log`) are created as root and Apache (www-data) cannot write to
> them → "could not be opened in append mode: Permission denied" error. Ad-hoc fix:
> `docker compose exec prestashop chown -R www-data:www-data var/logs var/cache`.

Shop: <http://localhost:8088> · Back office: <http://localhost:8088/admin-dev>
(`admin@example.com` / `prestashop123`). The module can also be enabled in BO → *Modules*.

## Configuration (BO → Modules → Lowest Shipping Cost → Configure)

The settings page is a **Symfony controller + `FormType`** rendered with Twig (not
HelperForm) — details in the "Code structure and quality" section.

- **Destination** — which destination the minimum is computed for:
  - *Visitor location* (default) — the visitor's country (geolocation / customer address, fallback to the default country),
  - *Shop default country* — the shop's default country only,
  - *Global minimum* — the minimum across all active countries.
- **Consider all customer groups** — theoretical minimum across every group
  (default: only the current visitor's group).
- **Skip external carriers** — skip external carriers (default: yes).
- **Show carrier and destination** — show the carrier name and country.

> **Geolocation:** a real per-visitor country for a guest requires enabling
> `PS_GEOLOCATION_ENABLED` + the GeoLite database. Without it the *Visitor location*
> mode uses the shop's default country.

## Verification

1. Open any demo product → the "Delivery from …" block appears in the price area.
2. **Quick View**: on a category/home listing, open a product's Quick View → the same
   block appears in the modal's price area.
3. **Quantity**: change the quantity selector → the price recomputes live (weight /
   value / additional cost × quantity); crossing the free-shipping threshold → "Free".
4. **Variant**: pick another combination → the cost reflects the variant's weight/price.
5. **Location**: compare *Visitor location* (e.g. a country with VAT) with *Global
   minimum* (the cheapest, often tax-free country); log in a customer with an address in another zone.
6. Set `additional_shipping_cost` / `shipping_handling` → the cost grows by those components.
7. A carrier with `range_behavior` = "disable" and a weight out of range → it disappears from the candidates.
8. Virtual product → no-shipping message.
9. Cross-check: add the selected quantity/variant to the cart, address = the resulting
   country, pick the winning carrier — the checkout cost equals the value on the product page.

## Cache

Caching is handled by the `src/Calculator/CachedCalculator` decorator (wrapping
`LowestShippingCostCalculator`): the result goes into PrestaShop's `Cache` layer under
a key that depends on the product, **variant, quantity, destination**, currency, shop
and group. Changing a carrier/product or saving the configuration **invalidates the
cache** — `CachedCalculator::invalidate()` / saving the settings bump the version
embedded in the key.

## Code structure and quality

The module follows modern PrestaShop 9 module conventions:

- **PSR-4 + Composer** — the logic lives in `src/` under the `PrestaShop\Module\LowestShippingCost`
  namespace, autoloaded via `composer.json` (`type: prestashop-module`, `prepend-autoloader: false`),
  loaded in the main file (`vendor/autoload.php`). No manual `require_once`. The module's
  main file is a **thin adapter** (hook wiring + delegation); all logic is in `src/`:
  pricing (`Calculator/LowestShippingCostCalculator`), tax mode (`Calculator/TaxContext`),
  cache (`Calculator/CachedCalculator`), input resolution (`Resolver/ProductPageResolver`),
  configuration (`Configuration/ConfigurationData`).
- **Configuration backend = Symfony + DI** — the settings page is a Symfony controller
  (`src/Controller/Admin/ConfigurationController`, based on `PrestaShopAdminController` —
  the PS 9 pattern, successor to the deprecated `FrameworkBundleAdminController`) + a
  `FormType` (`src/Form/ConfigurationType`, `ChoiceType` + `SwitchType`) rendered with Twig
  (`views/templates/admin/`). In PS 9 the controller must be a service: registered in
  `config/services.yml` with `autowire` + the `controller.service_arguments` tag (the
  `ConfigurationData` dependency injected via the constructor), a route in `config/routes.yml`,
  and `getContent()` redirects to that route. Access is guarded by a hidden admin Tab
  (`$this->tabs`). **The front-office hook intentionally creates the calculator with `new`** —
  in the front-office context `Module::get()` uses the lightweight legacy container, which does
  not contain the module's services (they live in the Symfony/admin container).
- **`declare(strict_types=1)`** and typed signatures in `src/` (`php >=8.1`, as required by
  PrestaShop 9).
- **AFL-3.0 license header** in PHP files, `logo.png` for the module list in BO.
- **Translations** via `$this->trans(..., 'Modules.Lowestshippingcost.Admin')` (PHP) and
  `{l s d='Modules.Lowestshippingcost.Shop'}` (templates) — the new translation system.

```bash
composer install              # dev dependencies + autoloader
composer test                 # PHPUnit (vendor/bin/phpunit)
vendor/bin/php-cs-fixer fix    # style (@PSR12 + @Symfony, tuned for PrestaShop)
```

**Tests** (`tests/`): unit tests, without booting PrestaShop — the pricing core
(`computeForCarrierZone`: weight/price method, free-shipping thresholds, `range_behavior`,
handling fee, additional cost, tax + rounding) is tested via the core test doubles defined
in `tests/bootstrap.php`, plus the tax display modes (`TaxContext`) and the immutability of
the `CalculationResult` DTO.
