<?php
/**
 * Configurable key transport for the SimpleSAMLphp test IdP.
 *
 * SimpleSAMLphp hardcodes rsa-oaep-mgf1p key transport (SAML2/IdP/SAML2.php)
 * and AES-128-CBC block encryption (saml2/EncryptedAssertion.php), and the
 * bundled xmlseclibs can only do SHA-1 OAEP. None of that is configurable, so
 * this file re-wraps the content encryption key after the fact using phpseclib
 * and rewrites the algorithm declarations to match.
 *
 * Copied into the vendored saml2 library at build time and called from the hook
 * added by patches/key-transport.patch. It does nothing unless the mounted IdP
 * config asks for it - see "Key Transport Algorithms" in the README.
 */

function totara_key_transport_config(): array {
    $defaults = [
        // 'stock'    - leave SimpleSAMLphp alone (rsa-oaep-mgf1p + aes128-cbc)
        // 'rsa-oaep' - xmlenc11#rsa-oaep with a configurable digest and MGF1 hash
        'mode'   => 'stock',
        'block'  => 'aes256-gcm', // aes128-cbc | aes256-cbc | aes128-gcm | aes256-gcm
        'digest' => 'sha256',     // OAEP digest
        'mgf'    => 'sha256',     // MGF1 hash
    ];

    // Preferred source: the 'key_transport' key of the mounted IdP config, so
    // the setting lives with the rest of the local IdP configuration and
    // survives the container being recreated.
    foreach (['/var/www/custom-saml20-idp-hosted.php', '/var/www/key-transport.php'] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $custom = include $path;
        if (!is_array($custom)) {
            continue;
        }
        if (isset($custom['key_transport']) && is_array($custom['key_transport'])) {
            return $custom['key_transport'] + $defaults;
        }
        if (isset($custom['mode'])) {
            return $custom + $defaults;
        }
    }

    return $defaults;
}

function totara_key_transport_autoload(): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    spl_autoload_register(static function (string $class): void {
        foreach ([
            'phpseclib3\\' => '/var/www/html/vendor/phpseclib/phpseclib/phpseclib/',
            'ParagonIE\\ConstantTime\\' => '/var/www/html/vendor/paragonie/constant_time_encoding/src/',
        ] as $prefix => $base) {
            if (str_starts_with($class, $prefix)) {
                $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
                return;
            }
        }
    });
}

/** Map an xmlenc block-cipher shorthand to its URI and key size. */
function totara_key_transport_block_algorithm(string $name): array {
    return match ($name) {
        'aes128-cbc' => ['http://www.w3.org/2001/04/xmlenc#aes128-cbc', 16],
        'aes256-cbc' => ['http://www.w3.org/2001/04/xmlenc#aes256-cbc', 32],
        'aes128-gcm' => ['http://www.w3.org/2009/xmlenc11#aes128-gcm', 16],
        'aes256-gcm' => ['http://www.w3.org/2009/xmlenc11#aes256-gcm', 32],
        default      => throw new \Exception('key_transport: unsupported block algorithm ' . $name),
    };
}

/**
 * Rewrite the <xenc:EncryptedKey> inside an <xenc:EncryptedData> element so the
 * content encryption key is wrapped with RSA-OAEP using a configurable digest
 * and MGF1 hash, and the declarations say so.
 *
 * @param \DOMElement $encryptedData
 * @param string      $cek        raw content encryption key bytes
 * @param string      $publicPem  SP public certificate/key in PEM form
 */
function totara_key_transport_rewrap_key(\DOMElement $encryptedData, string $cek, string $publicPem, array $config): void {
    totara_key_transport_autoload();

    $xencNs   = 'http://www.w3.org/2001/04/xmlenc#';
    $xenc11Ns = 'http://www.w3.org/2009/xmlenc11#';
    $dsNs     = 'http://www.w3.org/2000/09/xmldsig#';

    $doc   = $encryptedData->ownerDocument;
    $xpath = new \DOMXPath($doc);
    $xpath->registerNamespace('xenc', $xencNs);

    $encKeyList = $xpath->query('.//xenc:EncryptedKey', $encryptedData);
    if ($encKeyList->length === 0) {
        throw new \Exception('key_transport: no EncryptedKey to rewrite');
    }
    /** @var \DOMElement $encKey */
    $encKey = $encKeyList->item(0);

    // Re-wrap the CEK with the requested OAEP parameters.
    $rsa = \phpseclib3\Crypt\PublicKeyLoader::load($publicPem)
        ->withPadding(\phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
        ->withHash($config['digest'])
        ->withMGFHash($config['mgf']);
    $wrapped = $rsa->encrypt($cek);

    // Replace the ciphertext.
    $cipherValue = $xpath->query('.//xenc:CipherData/xenc:CipherValue', $encKey)->item(0);
    if (!$cipherValue instanceof \DOMElement) {
        throw new \Exception('key_transport: no CipherValue in EncryptedKey');
    }
    $cipherValue->nodeValue = base64_encode($wrapped);

    // Replace the EncryptionMethod with xmlenc11#rsa-oaep plus its parameters.
    $oldMethod = $xpath->query('./xenc:EncryptionMethod', $encKey)->item(0);
    $newMethod = $doc->createElementNS($xencNs, 'xenc:EncryptionMethod');
    $newMethod->setAttribute('Algorithm', $xenc11Ns . 'rsa-oaep');

    $digestUri = match ($config['digest']) {
        'sha1'   => 'http://www.w3.org/2000/09/xmldsig#sha1',
        'sha256' => 'http://www.w3.org/2001/04/xmlenc#sha256',
        'sha384' => 'http://www.w3.org/2001/04/xmldsig-more#sha384',
        'sha512' => 'http://www.w3.org/2001/04/xmlenc#sha512',
        default  => throw new \Exception('key_transport: unsupported digest ' . $config['digest']),
    };
    $digest = $doc->createElementNS($dsNs, 'ds:DigestMethod');
    $digest->setAttribute('Algorithm', $digestUri);
    $newMethod->appendChild($digest);

    $mgf = $doc->createElementNS($xenc11Ns, 'xenc11:MGF');
    $mgf->setAttribute('Algorithm', $xenc11Ns . 'mgf1' . $config['mgf']);
    $newMethod->appendChild($mgf);

    if ($oldMethod instanceof \DOMElement) {
        $encKey->replaceChild($newMethod, $oldMethod);
    } else {
        $encKey->insertBefore($newMethod, $encKey->firstChild);
    }
}
