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

$metadata[$totara_instance . '/auth/saml2/sp/metadata.php'] = [
    'metadata-set' => 'saml20-idp-remote',
    'entityid' => $totara_instance . '/auth/saml2/sp/metadata.php',
    'SingleSignOnService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => $totara_instance . '/auth/saml2/www/saml2/idp/SSOService.php',
        ],
    ],
    'SingleLogoutService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => $totara_instance . '/auth/saml2/sp/saml2-logout.php',
        ],
    ],
    'AssertionConsumerService' => $totara_instance . '/auth/saml2/sp/saml2-acs.php',
    'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
    'contacts' => [
        [
            'emailAddress' => 'team.platform@totara.com',
            'contactType' => 'technical',
            'givenName' => 'Administrator',
        ],
    ],
];
