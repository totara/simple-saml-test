<?php

/**
 * SAML 2.0 remote SP metadata for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-sp-remote
 */

/*
 * Example SimpleSAMLphp SAML 2.0 SP
 */
$totara_instance = getenv('TOTARA_URL');

if (empty($totara_instance)) {
    die('No TOTARA_URL environment variable was defined.');
}

$instances = [];
if (str_contains($totara_instance, ',')) {
    $instances = explode(',', $totara_instance);
    array_walk($instances, fn($instance) => trim($instance));
} else {
    $instances[] = $totara_instance;
}

$config = \SimpleSAML\Configuration::getInstance();
$temp_dir = $config->getPathValue('tempdir');

// Load each metadata file
$load_metadata = function (string $domain, bool $reset = false) use ($temp_dir) {
    // Key it
    $filename = $temp_dir . sha1($domain) . '.xml';
    if (file_exists($filename) && !$reset) {
        return file_get_contents($filename);
    }

    $ch = curl_init($domain);
    $fp = fopen($filename, 'w');

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    curl_exec($ch);

    if (curl_error($ch)) {
        return false;
    }

    curl_close($ch);
    fclose($fp);

    return file_get_contents($filename);
};

$reset = isset($_GET['refresh_metadata']) && $_GET['refresh_metadata'] === 'y';

$metadata = [];
foreach ($instances as $totara_instance) {
    $xml = $load_metadata($totara_instance . '/auth/saml2/sp/metadata.php', $reset);

    \SimpleSAML\Utils\XML::checkSAMLMessage($xml, 'saml-meta');
    $entities = \SimpleSAML\Metadata\SAMLParser::parseDescriptorsString($xml);

    // get all metadata for the entities
    foreach ($entities as &$entity) {
        $data = $entity->getMetadata20SP();

        if (isset($data['entityDescriptor'])) {
            unset($data['entityDescriptor']);
        }

        $entity = [
            'saml20-sp-remote' => $data,
        ];
    }

    // transpose from $entities[entityid][type] to $output[type][entityid]
    $output = \SimpleSAML\Utils\Arrays::transpose($entities);

    $metadata = array_merge($metadata, $output['saml20-sp-remote']);
}
