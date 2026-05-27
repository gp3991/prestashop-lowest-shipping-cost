# Lowest Shipping Cost — run environment

A **PrestaShop 9** module that shows the **lowest possible delivery cost** on the
product page (matched to the visitor's location, quantity and variant). This
repository contains the module itself (`modules/lowestshippingcost/`) plus a
ready-made Docker environment to run it on a clean PrestaShop install without any
manual setup.

## Quick start

```bash
# 1. Module dependencies (PSR-4 autoloader — without it the src/ classes won't work)
cd modules/lowestshippingcost && composer install --no-dev && cd -

# 2. PrestaShop — auto-install (prestashop/prestashop:9.1-apache image + MariaDB)
docker compose up -d

# 3. Once the install finishes — enable the module (as www-data, to keep permissions intact)
docker compose exec -u www-data prestashop \
  php bin/console prestashop:module install lowestshippingcost
```

- **Shop:** <http://localhost:8088>
- **Back office:** <http://localhost:8088/admin-dev> (`admin@example.com` / `prestashop123`)

The module is bind-mounted live (`./modules/lowestshippingcost`), so code changes are
visible without rebuilding the image.

## Module documentation

How it works, the conditions taken into account, configuration, the tax model, caching
and tests — see [`modules/lowestshippingcost/README.md`](modules/lowestshippingcost/README.md).
