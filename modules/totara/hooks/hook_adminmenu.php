<?php

use SimpleSAML\Module;
use SimpleSAML\XHTML\Template;

/**
 * Hook to add the modinfo module to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 * @return void
 */
function totara_hook_adminmenu(Template &$template): void
{
    $new_menu = [];
    $inserted = false;
    foreach ($template->data['menu'] as $i => $link) {
        if (!$inserted && $i === 'logout') {
            $new_menu['tmanage_metadata'] = [
                'url' => Module::getModuleURL('totara/metadata'),
                'name' => '{totara:link_manage_metadata}',
            ];
            $inserted = true;
        }

        $new_menu[$i] = $link;
    }

    if (!$inserted) {
        $new_menu['tmanage_metadata'] = [
            'url' => Module::getModuleURL('totara/metadata'),
            'name' => '{totara:link_manage_metadata}',
        ];
    }

    $template->data['menu'] = $new_menu;
}
