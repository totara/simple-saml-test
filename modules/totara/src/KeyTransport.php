<?php

namespace SimpleSAML\Module\totara;

use SimpleSAML\Session;

/**
 * Per-session key transport presets for the test IdP.
 *
 * The chosen preset is kept in the SimpleSAMLphp session and read back by
 * totara_key_transport_config() in patches/totara-key-transport.php when an
 * assertion is encrypted. 'configured' means no session choice, so the mounted
 * file or the built-in default applies.
 */
class KeyTransport
{
    public const CONFIGURED = 'configured';

    /** Name of the login form and settings form field. */
    public const FIELD = 'totara_key_transport';

    protected const SESSION_TYPE = 'totara:key_transport';
    protected const SESSION_ID = 'preset';

    /**
     * Fixed presets. rsa-oaep labels are digest/MGF1 hash. Block cipher is not
     * part of a preset, it comes from the mounted file or the default.
     *
     * @return array preset name => [label, config or null]
     */
    public static function presets(): array {
        return [
            self::CONFIGURED => ['As configured', null],
            'stock' => ['stock (rsa-oaep-mgf1p)', ['mode' => 'stock']],
            'rsa-oaep-sha256-sha256' => ['rsa-oaep sha256/sha256', ['mode' => 'rsa-oaep', 'digest' => 'sha256', 'mgf' => 'sha256']],
            'rsa-oaep-sha256-sha1' => ['rsa-oaep sha256/sha1', ['mode' => 'rsa-oaep', 'digest' => 'sha256', 'mgf' => 'sha1']],
            'rsa-oaep-sha1-sha1' => ['rsa-oaep sha1/sha1', ['mode' => 'rsa-oaep', 'digest' => 'sha1', 'mgf' => 'sha1']],
            'rsa-oaep-sha512-sha512' => ['rsa-oaep sha512/sha512', ['mode' => 'rsa-oaep', 'digest' => 'sha512', 'mgf' => 'sha512']],
            'rsa-1_5' => ['rsa-1_5', ['mode' => 'rsa-1_5']],
        ];
    }

    /**
     * Preset name => label, for select lists.
     *
     * @return array
     */
    public static function options(): array {
        return array_map(static fn(array $preset) => $preset[0], self::presets());
    }

    /**
     * @return string The preset chosen in this session, or 'configured'.
     */
    public static function selected(): string {
        $preset = Session::getSessionFromRequest()->getData(self::SESSION_TYPE, self::SESSION_ID);
        return is_string($preset) && isset(self::presets()[$preset]) ? $preset : self::CONFIGURED;
    }

    /**
     * Store the preset for the rest of this IdP session.
     *
     * @param string $preset
     * @return void
     */
    public static function select(string $preset): void {
        if (!isset(self::presets()[$preset])) {
            throw new \Exception("Unknown key transport preset '{$preset}'. Use one of: " . implode(', ', array_keys(self::presets())) . '.');
        }

        $session = Session::getSessionFromRequest();
        if ($preset === self::CONFIGURED) {
            $session->deleteData(self::SESSION_TYPE, self::SESSION_ID);
        } else {
            $session->setData(self::SESSION_TYPE, self::SESSION_ID, $preset, Session::DATA_TIMEOUT_SESSION_END);
        }
    }

    /**
     * @return array|null Config keys of the session's preset, or null to use the file or default.
     */
    public static function session_config(): ?array {
        return self::presets()[self::selected()][1];
    }

    /**
     * The key transport the next encrypted assertion in this session will use.
     *
     * @return array mode, block, digest, mgf and source (session, file or default)
     */
    public static function effective_config(): array {
        require_once '/var/www/html/vendor/simplesamlphp/saml2/src/SAML2/totara-key-transport.php';
        return totara_key_transport_config();
    }
}
