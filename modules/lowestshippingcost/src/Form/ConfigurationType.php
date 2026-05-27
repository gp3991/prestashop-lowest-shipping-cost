<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
declare(strict_types=1);

namespace PrestaShop\Module\LowestShippingCost\Form;

use PrestaShop\Module\LowestShippingCost\Configuration\ConfigurationData;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Back-office configuration form (Symfony). Labels and choices are translated
 * through the form translation domain.
 */
class ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dest_mode', ChoiceType::class, [
                'label' => 'Destination',
                'help' => 'Which destination the lowest cost is computed for.',
                'choices' => [
                    'Visitor location (geolocation / customer address)' => ConfigurationData::DEST_VISITOR,
                    'Shop default country' => ConfigurationData::DEST_DEFAULT_COUNTRY,
                    'Global minimum (all countries)' => ConfigurationData::DEST_GLOBAL,
                ],
                'expanded' => false,
                'multiple' => false,
            ])
            ->add('all_groups', SwitchType::class, [
                'label' => 'Consider all customer groups',
                'help' => 'On: theoretical minimum across every group. Off: only carriers available to the current visitor group.',
                'required' => false,
            ])
            ->add('skip_external', SwitchType::class, [
                'label' => 'Skip external carriers',
                'help' => 'External carriers compute their price in their own module and cannot be reliably replicated.',
                'required' => false,
            ])
            ->add('show_details', SwitchType::class, [
                'label' => 'Show carrier and destination',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'Modules.Lowestshippingcost.Admin',
        ]);
    }
}
