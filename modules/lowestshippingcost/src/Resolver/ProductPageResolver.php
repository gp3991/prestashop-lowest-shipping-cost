<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
declare(strict_types=1);

namespace PrestaShop\Module\LowestShippingCost\Resolver;

use Address;
use Configuration;
use Context;
use Country;
use PrestaShop\Module\LowestShippingCost\Configuration\ConfigurationData;
use Product;
use Tools;
use Validate;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Resolves the calculation inputs for the product page from the hook params,
 * the request and the shop context: product, selected combination, quantity and
 * the destination countries to scan. Keeps that resolution logic out of the
 * (legacy) module class so it stays a thin adapter.
 */
class ProductPageResolver
{
    private Context $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Loads the Product object referenced by the hook. The hook passes the
     * presented product (array / ProductLazyArray), so we extract its id and
     * load the authoritative ObjectModel, falling back to the request.
     *
     * @param array<string, mixed> $params hook parameters
     */
    public function resolveProduct(array $params): Product
    {
        $idProduct = 0;
        if (isset($params['product'])) {
            $p = $params['product'];
            if (is_array($p) && isset($p['id_product'])) {
                $idProduct = (int) $p['id_product'];
            } elseif (is_object($p) && isset($p->id_product)) {
                $idProduct = (int) $p->id_product;
            } elseif (is_object($p) && isset($p->id)) {
                $idProduct = (int) $p->id;
            }
        }
        if (!$idProduct) {
            $idProduct = (int) Tools::getValue('id_product');
        }

        return new Product($idProduct, false, (int) $this->context->language->id);
    }

    /**
     * Selected combination id from the presented product or the request (0 = none).
     *
     * @param array<string, mixed> $params hook parameters
     */
    public function resolveProductAttribute(array $params): int
    {
        if (isset($params['product'])) {
            $p = $params['product'];
            if (is_array($p) && isset($p['id_product_attribute'])) {
                return (int) $p['id_product_attribute'];
            }
            if (is_object($p) && isset($p->id_product_attribute)) {
                return (int) $p->id_product_attribute;
            }
        }

        return (int) Tools::getValue('id_product_attribute');
    }

    /**
     * Quantity selected on the page, clamped to the product/combination minimum.
     *
     * @param array<string, mixed> $params hook parameters
     */
    public function resolveQuantity(array $params): int
    {
        $minimal = 1;
        $presented = 1;
        if (isset($params['product']) && is_array($params['product'])) {
            $p = $params['product'];
            $minimal = isset($p['minimal_quantity']) ? (int) $p['minimal_quantity'] : 1;
            $presented = isset($p['quantity_wanted']) ? (int) $p['quantity_wanted'] : $minimal;
        }
        $qty = (int) Tools::getValue('quantity_wanted', $presented);

        return max(1, $minimal, $qty);
    }

    /**
     * Destination countries to scan for the given mode. Returns rows with
     * id_country, id_zone, name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolveDestinationCountries(string $destMode): array
    {
        $idLang = (int) $this->context->language->id;
        switch ($destMode) {
            case ConfigurationData::DEST_GLOBAL:
                return Country::getCountries($idLang, true);
            case ConfigurationData::DEST_DEFAULT_COUNTRY:
                return [$this->countryRow((int) Configuration::get('PS_COUNTRY_DEFAULT'), $idLang)];
            case ConfigurationData::DEST_VISITOR:
            default:
                return [$this->countryRow($this->resolveVisitorCountryId(), $idLang)];
        }
    }

    /**
     * Visitor's destination country: the logged-in customer's delivery address
     * country, otherwise the (geolocation-aware) context country, otherwise the
     * shop default country.
     */
    private function resolveVisitorCountryId(): int
    {
        $customer = $this->context->customer;
        if ($customer && $customer->id) {
            $idAddress = 0;
            $cart = $this->context->cart;
            if ($cart && $cart->id_address_delivery && Address::addressExists((int) $cart->id_address_delivery, true)) {
                $idAddress = (int) $cart->id_address_delivery;
            } else {
                $idAddress = (int) Address::getFirstCustomerAddressId((int) $customer->id);
            }
            if ($idAddress) {
                $address = new Address($idAddress);
                if (Validate::isLoadedObject($address) && $address->id_country) {
                    return (int) $address->id_country;
                }
            }
        }
        if ($this->context->country && $this->context->country->id) {
            return (int) $this->context->country->id;
        }

        return (int) Configuration::get('PS_COUNTRY_DEFAULT');
    }

    /**
     * Builds a country row (id_country, id_zone, name), falling back to the shop
     * default country when the given one is missing or inactive.
     *
     * @return array<string, mixed>
     */
    private function countryRow(int $idCountry, int $idLang): array
    {
        $country = new Country($idCountry, $idLang);
        if (!Validate::isLoadedObject($country) || !$country->active) {
            $country = new Country((int) Configuration::get('PS_COUNTRY_DEFAULT'), $idLang);
        }

        return [
            'id_country' => (int) $country->id,
            'id_zone' => (int) $country->id_zone,
            'name' => is_array($country->name) ? reset($country->name) : $country->name,
        ];
    }
}
