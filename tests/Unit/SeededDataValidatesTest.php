<?php

namespace Goldnead\StatamicConsent\Tests\Unit;

use Goldnead\StatamicConsent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;

/**
 * The install command seeds the global set from the config file, and the client
 * then edits it behind the shipped blueprint. If the blueprint demands a field
 * the seed leaves empty, the whole screen refuses to save on first open — the
 * client cannot change a single label until they fill in fields they never
 * created. That happened: the four seeded categories carry only a handle,
 * because their names come from the translation files, while the blueprint
 * marked the name as required.
 */
class SeededDataValidatesTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function blueprint(): array
    {
        return Yaml::parseFile(__DIR__.'/../../resources/blueprints/globals/consent.yaml');
    }

    /**
     * @return list<string>
     */
    protected function requiredFieldsOfSet(string $tab, string $repeaterHandle, string $set): array
    {
        $fields = $this->blueprint()['tabs'][$tab]['sections'][0]['fields'][0]['field']['sets'][$set]['fields'] ?? [];

        return collect($fields)
            ->filter(fn (array $field): bool => in_array('required', $field['field']['validate'] ?? [], true))
            ->pluck('handle')
            ->values()
            ->all();
    }

    #[Test]
    public function every_required_category_field_is_seeded(): void
    {
        $required = $this->requiredFieldsOfSet('categories', 'categories', 'category');

        foreach (config('statamic-consent.categories') as $category) {
            foreach ($required as $field) {
                $this->assertNotEmpty(
                    $category[$field] ?? null,
                    "The blueprint requires \"{$field}\" on a category, but the shipped config seeds a category without it. The control panel would refuse to save on first open."
                );
            }
        }
    }

    #[Test]
    public function every_required_service_field_is_seeded(): void
    {
        $required = $this->requiredFieldsOfSet('services', 'services', 'service');

        foreach (config('statamic-consent.services') as $service) {
            foreach ($required as $field) {
                $this->assertNotEmpty(
                    $service[$field] ?? null,
                    "The blueprint requires \"{$field}\" on a service, but the shipped config seeds a service without it."
                );
            }
        }
    }

    #[Test]
    public function every_seeded_service_points_at_a_seeded_category(): void
    {
        $categories = collect(config('statamic-consent.categories'))->pluck('handle')->all();

        foreach (config('statamic-consent.services') as $service) {
            $this->assertContains(
                $service['category'],
                $categories,
                "Service \"{$service['handle']}\" sits in category \"{$service['category']}\", which is not shipped — it would never appear in the dialog."
            );
        }
    }

    #[Test]
    public function every_shipped_category_option_in_the_blueprint_exists_in_the_config(): void
    {
        $fields = $this->blueprint()['tabs']['services']['sections'][0]['fields'][0]['field']['sets']['service']['fields'];
        $options = collect($fields)->firstWhere('handle', 'category')['field']['options'];
        $configured = collect(config('statamic-consent.categories'))->pluck('handle')->all();

        foreach (array_keys($options) as $handle) {
            $this->assertContains(
                $handle,
                $configured,
                "The blueprint offers category \"{$handle}\", but no such category is configured. Picking it would silently drop the service from the dialog."
            );
        }
    }
}
