<?php

/**
 * Hook to add the modinfo module to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 * @return void
 */
function totara_hook_frontpage(&$links)
{
    assert(is_array($links));
    assert(array_key_exists('links', $links));

    $links['federation']['manage_metadata'] = [
        'href' => SimpleSAML\Module::getModuleURL('totara/manage_metadata.php'),
        'text' => '{totara:link_manage_metadata}',
    ];
}
