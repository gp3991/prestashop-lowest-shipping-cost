<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
declare(strict_types=1);

namespace PrestaShop\Module\LowestShippingCost\Controller\Admin;

use PrestaShop\Module\LowestShippingCost\Configuration\ConfigurationData;
use PrestaShop\Module\LowestShippingCost\Form\ConfigurationType;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Symfony back-office configuration page. The settings persistence is
 * delegated to the injected ConfigurationData service (dependency injection,
 * see config/services.yml).
 */
class ConfigurationController extends PrestaShopAdminController
{
    /** @var ConfigurationData */
    private $configurationData;

    public function __construct(ConfigurationData $configurationData)
    {
        $this->configurationData = $configurationData;
    }

    public function indexAction(Request $request): Response
    {
        $form = $this->createForm(ConfigurationType::class, $this->configurationData->getConfiguration());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configurationData->saveConfiguration($form->getData());
            $this->addFlash('success', $this->trans('Successful update.', [], 'Admin.Notifications.Success'));

            return $this->redirectToRoute('lowestshippingcost_configuration');
        }

        return $this->render('@Modules/lowestshippingcost/views/templates/admin/configure.html.twig', [
            'configurationForm' => $form->createView(),
            'enableSidebar' => true,
        ]);
    }
}
