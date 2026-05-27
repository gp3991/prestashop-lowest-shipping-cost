<?php
/**
 * Lowest Shipping Cost - PrestaShop 9 module.
 *
 * @author    Recruitment Task
 * @copyright 2026 Recruitment Task
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

// Run with: vendor/bin/php-cs-fixer fix
// Requires: composer require --dev friendsofphp/php-cs-fixer
$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->append([__FILE__, __DIR__ . '/lowestshippingcost.php']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'declare_strict_types' => false,
        'yoda_style' => false,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'phpdoc_align' => false,
        'no_superfluous_phpdoc_tags' => false,
        // Keep PrestaShop-style imports of core classes (no leading-backslash FQN).
        'fully_qualified_strict_types' => false,
        'global_namespace_import' => false,
        // Keep the file-level license header tight to the opening tag.
        'blank_line_after_opening_tag' => false,
    ])
    ->setFinder($finder);
