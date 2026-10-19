<?php

namespace SimpleSAML\Module\totara\Auth\Process;

use SimpleSAML\Auth\ProcessingFilter;
use SimpleSAML\Module\totara\KeyTransport as KeyTransportPresets;

/**
 * Saves the key transport picked on the IdP login page into the session.
 *
 * Runs in the same request as the login form post, before the response is
 * built and encrypted. When the IdP skips the login page (already logged in)
 * nothing is posted and the session's earlier choice stays.
 */
class KeyTransport extends ProcessingFilter
{
    /**
     * @param array $state
     * @return void
     */
    public function process(array &$state): void {
        if (!isset($_POST[KeyTransportPresets::FIELD])) {
            return;
        }

        KeyTransportPresets::select((string) $_POST[KeyTransportPresets::FIELD]);
    }
}
