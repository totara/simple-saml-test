<?php

/**
 * SAML 2.0 remote SP metadata for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-sp-remote
 */

use SimpleSAML\Module\totara\MetadataManager;

$manager = MetadataManager::make();

$metadata = [];
foreach ($manager->get_sp_list() as $key => $sp_instance) {

    if (empty($sp_instance['fetched'])) {
        continue;
    }

    $xml = $manager->get_metadata_file($key);

    // No data, silently ignore it.
    if (!$xml) {
        continue;
    }

    \SimpleSAML\Utils\XML::checkSAMLMessage($xml, 'saml-meta');
    $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($xml);

    // get all metadata for the entities
    foreach ($entities as &$entity) {
        $data = $entity->getMetadata20SP();

        // Hack to get en-US behaving
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['en-US'])) {
                $data[$key]['en'] = $value['en-US'];
            }
        }

        if (isset($data['entityDescriptor'])) {
            unset($data['entityDescriptor']);
        }

        $data['name']['en'] .= ' - ' . $sp_instance['url'];

        $entity = [
            'saml20-sp-remote' => $data,
        ];
    }

    // transpose from $entities[entityid][type] to $output[type][entityid]
    $output = \SimpleSAML\Utils\Arrays::transpose($entities);

    $metadata = array_merge($metadata, $output['saml20-sp-remote']);
}
