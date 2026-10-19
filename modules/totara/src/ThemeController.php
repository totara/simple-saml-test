<?php

namespace SimpleSAML\Module\totara;

use SimpleSAML\Auth\State;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\XHTML\TemplateControllerInterface;
use Twig\Environment;

/**
 * Theme controller for the totara:simple theme.
 *
 * Gives the login page override (themes/simple/core/loginuserpass.twig) the
 * key transport presets and the session's current choice.
 */
class ThemeController implements TemplateControllerInterface
{
    /**
     * @param Environment $twig
     * @return void
     */
    public function setUpTwig(Environment &$twig): void {
    }

    /**
     * @param array $data
     * @return void
     */
    public function display(array &$data): void {
        // Only a login for a service provider runs authproc.idp, which saves
        // the choice. The admin login does not, so it gets no picker.
        if (empty($data['AuthState'])) {
            return;
        }
        $state = State::loadState($data['AuthState'], UserPassBase::STAGEID, true);
        if (!isset($state['SPMetadata'])) {
            return;
        }

        $data['key_transport_field'] = KeyTransport::FIELD;
        $data['key_transport_options'] = KeyTransport::options();
        $data['key_transport_selected'] = KeyTransport::selected();
    }
}
