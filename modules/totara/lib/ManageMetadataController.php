<?php

namespace SimpleSAML\Module\totara;

use SimpleSAML\Configuration;
use SimpleSAML\Error\Exception;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for the statistics module.
 *
 * This class serves the statistics views available in the module.
 *
 * @package SimpleSAML\Module\admin
 */
class ManageMetadataController {
    /** @var Configuration */
    protected $config;

    /** @var Session */
    protected $session;

    /** @var MetadataManager */
    protected MetadataManager $metadata_manager;

    public function __construct(Configuration $config, Session $session) {
        $this->config = $config;
        $this->session = $session;
        $this->metadata_manager = MetadataManager::make();
    }

    /**
     * Display the main admin page.
     *
     * @return Template
     * @throws Exception
     */
    public function main(Request $request) {
        Utils\Auth::requireAdmin();

        if ($request->isMethod('post')) {
            $url = trim($request->request->get('metadata_url') ?? '');

            // Handle URLs
            if (!empty($url)) {
                $this->metadata_manager->add_url($url);
                Utils\HTTP::redirectTrustedURL(Module::getModuleURL('totara/manage_metadata.php#saved'));
                exit;
            }
        }

        $error = '';

        $action = $request->query->get('action');
        if ($action === 'delete' || $action === 'refresh') {
            $key = $request->query->get('key');
            if (empty($key)) {
                throw new \Exception('No key was provided');
            }

            if ($action === 'delete') {
                $result = $this->metadata_manager->delete_entity($key);

                Utils\HTTP::redirectTrustedURL(Module::getModuleURL('totara/manage_metadata.php?result=delete-' . ($result ? 'succeeded' : 'failed')));
                exit;
            }

            if ($action === 'refresh') {
                $result = $this->metadata_manager->refresh_entity($key);

                Utils\HTTP::redirectTrustedURL(Module::getModuleURL('totara/manage_metadata.php?result=refresh-' . ($result ? 'succeeded' : 'failed')));
                exit;
            }
        }

        // Load existing metadata
        $list = $this->metadata_manager->get_sp_list();
        $now = time();

        foreach ($list as $key => $value) {
            if (!isset($value['url']) || !$value['fetched']) {
                unset($list[$key]);
                continue;
            }

            $list[$key]['manage'] = Module::getModuleURL('core/show_metadata.php?entityid=' . $value['url'] . '&set=saml20-sp-remote');
            $list[$key]['delete'] = Module::getModuleURL('totara/manage_metadata.php?action=delete&key=' . $key);
            $list[$key]['refresh'] = Module::getModuleURL('totara/manage_metadata.php?action=refresh&key=' . $key);

            $diff = round(($now - $value['fetched']));

            if ($diff < 10) {
                $list[$key]['time'] = 'Within the last 10 seconds';
            } else if ($diff < 60) {
                $list[$key]['time'] = $diff . ' seconds ago';
            } else {
                $diff = round(($now - $value['fetched']) / 60);
                if ($diff > 1) {
                    $list[$key]['time'] = $diff . ' mins ago';
                } else {
                    $list[$key]['time'] = 'Within the last minute';
                }
            }
        }

        /**
         * Prepare template.
         */
        $t = new Template($this->config, 'totara:manage_metadata');
        $t->data = [
            'pageid' => 'manage_metadata',
            'isadmin' => \SimpleSAML\Utils\Auth::isAdmin(),
            'logouturl' => \SimpleSAML\Utils\Auth::getAdminLogoutURL(),
            'loginurl' => \SimpleSAML\Utils\Auth::getAdminLoginURL(),
            'sp_list' => $list,
            'error' => $error,
            'coremodurl' => Module::getModuleURL('core/')
        ];

        return $t;
    }
}
