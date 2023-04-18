<?php

/**
 * SAML 2.0 remote SP metadata for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-sp-remote
 */

use SimpleSAML\Module\totara\Metadata\Manager;
use SimpleSAML\Utils\Arrays;

$manager = Manager::make();

$metadata = [];
foreach ($manager->get_sp_list() as $key => $sp_instance) {
    if (empty($sp_instance['fetched'])) {
        continue;
    }

    $entities = [];
    foreach ($sp_instance['entities'] as $entity) {
        $entity_id = $entity['entity_id'];
        $name = $entity['name'];
        $data = json_decode($manager->get_metadata_file($entity['file']), true);

        // Hack to get en-US behaving
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['en-US'])) {
                $data[$key]['en'] = $value['en-US'];
            }
        }

        if (isset($data['entityDescriptor'])) {
            unset($data['entityDescriptor']);
        }

        $data['name']['en'] .= ' - ' . $entity_id;
        $entities[$entity_id] = [
            'saml20-sp-remote' => $data,
        ];
    }

    // transpose from $entities[entityid][type] to $output[type][entityid]
    $output = (new Arrays())->transpose($entities);

    $metadata = array_merge($metadata, $output['saml20-sp-remote']);
}
