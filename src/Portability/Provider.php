<?php
declare(strict_types=1);

namespace CB\Notes\Portability;

use CoreBlueprint\Core\DataExchange\EntityInterface;
use CoreBlueprint\Core\DataExchange\Foundation;
use CoreBlueprint\Core\Interoperability\Registry;

/** Public Base Data Exchange registration; no HTTP endpoints or import mutations. */
final class Provider {
    public static function register(): void {
        if (!interface_exists(EntityInterface::class) || !class_exists(Foundation::class)) {
            return;
        }
        Registry::register_implementation([
            'provider' => 'core-blueprint-notes',
            'id' => 'personal-notes',
            'label' => __('Notes', 'core-blueprint-notes'),
            'description' => __('Private personal notes edited in Gutenberg.', 'core-blueprint-notes'),
            'contract_owner' => Foundation::CONTRACT_OWNER,
            'contract' => Foundation::CONTRACT_ID,
            'contract_version' => Foundation::CONTRACT_VERSION,
            'supports' => [Foundation::SUPPORT_EXPORT, Foundation::SUPPORT_IMPORT, Foundation::SUPPORT_JSON],
            'factory' => static fn(): NotesEntity => new NotesEntity(),
        ]);
    }
}
