{**
 * Lowest shipping cost block on the product page.
 * Variables are assigned by hookDisplayProductAdditionalInfo().
 *}
{if $lsc_status == 'ok'}
  <div class="lsc-block lsc-block--ok">
    <i class="material-icons lsc-icon" aria-hidden="true">local_shipping</i>
    <span class="lsc-label">{l s='Delivery from' d='Modules.Lowestshippingcost.Shop'}</span>
    <span class="lsc-price">{$lsc_cost_formatted}</span>
    {if $lsc_show_details && $lsc_carrier_name}
      <span class="lsc-details">{l s='(%carrier%, to %country%)' sprintf=['%carrier%' => $lsc_carrier_name, '%country%' => $lsc_country_name] d='Modules.Lowestshippingcost.Shop'}</span>
    {/if}
  </div>
{elseif $lsc_status == 'free'}
  <div class="lsc-block lsc-block--free">
    <i class="material-icons lsc-icon" aria-hidden="true">local_shipping</i>
    <span class="lsc-label">{l s='Free delivery available' d='Modules.Lowestshippingcost.Shop'}</span>
    {if $lsc_show_details && $lsc_carrier_name}
      <span class="lsc-details">{l s='via %carrier%' sprintf=['%carrier%' => $lsc_carrier_name] d='Modules.Lowestshippingcost.Shop'}</span>
    {/if}
  </div>
{elseif $lsc_status == 'virtual'}
  <div class="lsc-block lsc-block--virtual">
    <i class="material-icons lsc-icon" aria-hidden="true">cloud_download</i>
    <span class="lsc-label">{l s='Digital product — no shipping' d='Modules.Lowestshippingcost.Shop'}</span>
  </div>
{/if}
