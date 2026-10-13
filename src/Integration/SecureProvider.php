<?php
declare(strict_types=1);

namespace CB\Notes\Integration;

defined('ABSPATH') || exit;

/**
 * Optional adapter bootstrap stays loadable even when Secure Share is absent.
 * The implementation is resolved only once its public interface exists.
 */
final class SecureProvider {
    public static function available(): bool {
        return interface_exists('CB\\SecureShare\\PublicApi\\ResourceProvider')
            && class_exists('CB\\SecureShare\\PublicApi\\SecureShare')
            && \CB\SecureShare\PublicApi\SecureShare::isAvailable();
    }

    public static function register(): void {
        if (!self::available()) {
            return;
        }
        \CB\SecureShare\PublicApi\SecureShare::registerProvider(SecureShareResource::instance());
    }
}
