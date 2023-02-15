<?php

/**
 * SAML 2.0 remote SP metadata for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-sp-remote
 */

/*
 * Example SimpleSAMLphp SAML 2.0 SP
 */
$sp_instance = getenv('SP_URLS');

if (empty($sp_instance)) {
    echo 'No SP_URLS environment variable were defined.';
    return;
}

$instances = [];
if (str_contains($sp_instance, ',')) {
    $instances = explode(',', $sp_instance);
    array_walk($instances, fn($instance) => trim($instance));
} else {
    $instances[] = $sp_instance;
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
        // Silently continue
        curl_close($ch);
        fclose($fp);
        return false;
    }

    curl_close($ch);
    fclose($fp);

    return file_get_contents($filename);
};

$refresh = isset($_GET['refresh_metadata']) && (in_array($_GET['refresh_metadata'], ['y', 'yes', 't', 'true', 1, '1'], true));

$metadata = [];
foreach ($instances as $sp_instance) {
    $xml = $load_metadata($sp_instance, $refresh);

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

        $entity = [
            'saml20-sp-remote' => $data,
        ];
    }

    // transpose from $entities[entityid][type] to $output[type][entityid]
    $output = \SimpleSAML\Utils\Arrays::transpose($entities);

    $metadata = array_merge($metadata, $output['saml20-sp-remote']);
}

if ($refresh) {
    $new_url = str_replace('refresh_metadata=y', 'refreshed=done', $_SERVER['REQUEST_URI']);
    header('Location: ' . $new_url);
    exit;
}
