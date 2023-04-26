<?php

namespace SimpleSAML\Module\totara\Controller;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Locale\Translate;
use SimpleSAML\Module;
use SimpleSAML\Module\admin\Controller\Menu;
use SimpleSAML\Module\totara\Metadata\Manager;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for the manage metadata endpoint
 */
class ManageMetadata {
    /** @var Configuration */
    protected Configuration $config;

    /** @var Manager */
    protected Manager $metadata_manager;

    /** @var Menu */
    protected Menu $menu;

    public function __construct() {
        $this->config = Configuration::getInstance();;
        $this->metadata_manager = Manager::make();
        $this->menu = new Menu();
    }

    /**
     * @param Request $request
     * @param string $key
     * @return void
     * @throws \SimpleSAML\Error\Exception
     */
    public function refresh(Request $request, string $key): void {
        (new Utils\Auth())->requireAdmin();

        if (empty($key)) {
            throw new \Exception('No key was provided');
        }

        $result = $this->metadata_manager->refresh_entity($key);
        (new Utils\HTTP())->redirectTrustedURL(Module::getModuleURL('totara/metadata?result=refresh-' . ($result ? 'succeeded' : 'failed')));
        exit;
    }

    /**
     * @param Request $request
     * @param string $key
     * @return void
     * @throws \SimpleSAML\Error\Exception
     */
    public function delete(Request $request, string $key): void {
        (new Utils\Auth())->requireAdmin();

        if (empty($key)) {
            throw new \Exception('No key was provided');
        }

        $result = $this->metadata_manager->delete_entity($key);
        (new Utils\HTTP())->redirectTrustedURL(Module::getModuleURL('totara/metadata?result=delete-' . ($result ? 'succeeded' : 'failed')));
        exit;
    }

    /**
     * Display the main admin page.
     *
     * @param Request $request
     * @return Template
     */
    public function main(Request $request): Template {
        (new Utils\Auth())->requireAdmin();

        if ($request->isMethod('post')) {
            $url = trim($request->request->get('metadata_url') ?? '');

            // Handle URLs
            if (!empty($url)) {
                $this->metadata_manager->add_url($url);
                (new Utils\HTTP())->redirectTrustedURL(Module::getModuleURL('totara/metadata#saved'));
                exit;
            }
        }

        $error = '';

        // Load existing metadata
        $sps = $this->metadata_manager->get_sp_list();
        $now = time();
        $list = [];

        foreach ($sps as $key => $entries) {
            if (!isset($entries['url']) || !$entries['fetched']) {
                unset($sps[$key]);
                continue;
            }
            $diff = round(($now - $entries['fetched']));

            if ($diff < 10) {
                $time = 'Within the last 10 seconds';
            } else if ($diff < 60) {
                $time = $diff . ' seconds ago';
            } else {
                $diff = round(($now - $entries['fetched']) / 60);
                if ($diff > 1) {
                    $time = $diff . ' mins ago';
                } else {
                    $time = 'Within the last minute';
                }
            }

            $list[$key] = [
                'url' => $entries['url'],
                'delete' => Module::getModuleURL('totara/metadata/delete/' . $key),
                'refresh' => Module::getModuleURL('totara/metadata/refresh/' . $key),
                'time' => $time,
                'entities' => [],
            ];

            foreach ($entries['entities'] as $entity) {
                $list[$key]['entities'][] = [
                    'manage' => Module::getModuleURL('admin/federation/show?entityid=' . urlencode($entity['entity_id']) . '&set=saml20-sp-remote'),
                    'name' => $entity['name'],
                    'entity_id' => $entity['entity_id'],
                    'idp_login' => Module::getModuleURL('saml/idp/singleSignOnService', ['spentityid' => $entity['entity_id']]),
                ];
            }
        }

        /**
         * Prepare template.
         */
        $t = new Template($this->config, 'totara:manage_metadata.twig');

        $t->data = [
            'pageid' => 'manage_metadata',
            'isadmin' => (new Utils\Auth())->isAdmin(),
            'logouturl' => (new Utils\Auth())->getAdminLogoutURL(),
            'idp_logout' => Module::getModuleURL('saml/idp/singleLogout', ['ReturnTo' => Module::getModuleURL('admin/federation')]),
            'sp_list' => $list,
            'error' => $error,
        ];

        Module::callHooks('federationpage', $t);
        Assert::isInstanceOf($t, Template::class);

        $this->menu->addOption('logout', $t->data['logouturl'], Translate::noop('Log out'));
        /** @psalm-var Template $t */
        return $this->menu->insert($t);
    }
}
