<?php

namespace SimpleSAML\Module\totara\Controller;

use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Module;
use SimpleSAML\Module\totara\KeyTransport;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Front page of the test IdP: what it will send right now, and a form to pick
 * the key transport for this session. Public, like the login page.
 */
class Settings {
    /** @var Configuration */
    protected Configuration $config;

    public function __construct() {
        $this->config = Configuration::getInstance();
    }

    /**
     * @param Request $request
     * @return Template
     */
    public function main(Request $request): Template {
        if ($request->isMethod('post')) {
            KeyTransport::select((string) $request->request->get(KeyTransport::FIELD, ''));
            (new Utils\HTTP())->redirectTrustedURL(Module::getModuleURL('totara/'));
            exit;
        }

        $handler = MetaDataStorageHandler::getMetadataHandler();
        $entity_id = $handler->getMetaDataCurrentEntityID('saml20-idp-hosted');
        $idp = $handler->getMetaDataConfig($entity_id, 'saml20-idp-hosted');

        $t = new Template($this->config, 'totara:settings.twig');
        $t->data = [
            'pageid' => 'totara_settings',
            'encryption' => $idp->getOptionalBoolean('assertion.encryption', false),
            'key_transport' => $this->describe(KeyTransport::effective_config()),
            'key_transport_field' => KeyTransport::FIELD,
            'key_transport_options' => KeyTransport::options(),
            'key_transport_selected' => KeyTransport::selected(),
            'encryption_env' => getenv('ENCRYPTION') ?: 'off',
            'certificates' => getenv('CERTIFICATES') ?: 'default',
            'entity_id' => $entity_id,
            'sp_count' => count($handler->getList('saml20-sp-remote')),
            'logged_in' => (new Simple($idp->getString('auth')))->isAuthenticated(),
            'admin_url' => Module::getModuleURL('admin/'),
            'metadata_url' => Module::getModuleURL('totara/metadata'),
            'idp_logout' => Module::getModuleURL('saml/idp/singleLogout', ['ReturnTo' => Module::getModuleURL('totara/')]),
        ];

        return $t;
    }

    /**
     * What will go on the wire for a key transport config.
     *
     * @param array $config
     * @return array
     */
    protected function describe(array $config): array {
        return match ($config['mode']) {
            // Fixed by SimpleSAMLphp, whatever the config says.
            'stock' => [
                'mode' => 'stock (xmlenc#rsa-oaep-mgf1p)',
                'block' => 'aes128-cbc',
                'digest' => 'sha1',
                'mgf' => 'sha1',
                'source' => $config['source'],
            ],
            'rsa-1_5' => [
                'mode' => 'rsa-1_5 (xmlenc#rsa-1_5)',
                'block' => $config['block'],
                'digest' => 'none',
                'mgf' => 'none',
                'source' => $config['source'],
            ],
            // Any other mode in the mounted file is treated as rsa-oaep.
            default => [
                'mode' => 'rsa-oaep (xmlenc11#rsa-oaep)',
                'block' => $config['block'],
                'digest' => $config['digest'],
                'mgf' => $config['mgf'],
                'source' => $config['source'],
            ],
        };
    }
}
