<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    GlobalPayments
 * @copyright Since 2021 GlobalPayments
 * @license   LICENSE
 */

namespace GlobalPayments\PaymentGatewayProvider\Platform\Helper;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Encrypts the secrets stored in the Configuration table, using the shop's own encryption key.
 */
class EncryptionHelper
{
    /**
     * Placeholder used instead of a secret in logs and messages.
     */
    public const MASK = '********';

    /**
     * @param string $value
     *
     * @return string
     */
    public static function encrypt($value)
    {
        $value = (string) $value;

        if ('' === $value) {
            return '';
        }

        return (new \PhpEncryption(_NEW_COOKIE_KEY_))->encrypt($value);
    }

    /**
     * @param string|false $value
     *
     * @return string The decrypted value, or an empty string if the value cannot be decrypted
     */
    public static function decrypt($value)
    {
        if (!is_string($value) || '' === $value) {
            return '';
        }

        try {
            $decrypted = (new \PhpEncryption(_NEW_COOKIE_KEY_))->decrypt($value);
        } catch (\Throwable $e) {
            return '';
        }

        return is_string($decrypted) ? $decrypted : '';
    }

    /**
     * Replace every occurrence of the given secrets in a message.
     *
     * @param string $message
     * @param string[] $secrets
     *
     * @return string
     */
    public static function mask($message, array $secrets)
    {
        foreach ($secrets as $secret) {
            $secret = (string) $secret;

            if ('' === $secret) {
                continue;
            }

            $message = str_replace(
                [$secret, trim((string) json_encode($secret), '"'), htmlspecialchars($secret), rawurlencode($secret)],
                self::MASK,
                $message
            );
        }

        return $message;
    }
}
