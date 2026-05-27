# Lowest Shipping Cost — run environment

A **PrestaShop 9** module that shows the **lowest possible delivery cost** on the
product page and in the Quick View modal (matched to the visitor's location, quantity
and variant). This
repository contains the module itself (`modules/lowestshippingcost/`) plus a
ready-made Docker environment to run it on a clean PrestaShop install without any
manual setup.

## Quick start

```bash
# 1. PrestaShop — auto-install (prestashop/prestashop:9.1-apache image + MariaDB)
docker compose up -d

# 2. Once the install finishes — enable the module (as www-data, to keep permissions intact)
docker compose exec -u www-data prestashop \
  php bin/console prestashop:module install lowestshippingcost
```

No Composer step is needed to run it: the module ships its production autoloader
(`modules/lowestshippingcost/vendor/`, generated with `--no-dev`), which is the only
way PrestaShop loads a module's `src/` classes. Composer is only required for
development (tests / php-cs-fixer) — see the module README.

- **Shop:** <http://localhost:8088>
- **Back office:** <http://localhost:8088/admin-dev> (`admin@example.com` / `prestashop123`)

The module is bind-mounted live (`./modules/lowestshippingcost`), so code changes are
visible without rebuilding the image.

## Module documentation

How it works, the conditions taken into account, configuration, the tax model, caching
and tests — see [`modules/lowestshippingcost/README.md`](modules/lowestshippingcost/README.md).
