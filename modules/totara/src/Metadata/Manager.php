<?php

namespace SimpleSAML\Module\totara\Metadata;

use SimpleSAML\Error\Exception;
use SimpleSAML\Metadata\SAMLParser;
use SimpleSAML\Utils;

/**
 * Helper class handling management of the persisted SP metadata files.
 */
class Manager
{
    /**
     * Name of the json file that contains the list of metadata entries.
     */
    protected const CONFIG_FILE = 'metadata-config.json';

    /**
     * @var string Path to where our config files are kept.
     */
    protected string $config_path;

    /**
     * @var array|null Collection of content in the json config file.
     */
    private ?array $sp_list = null;

    /**
     * @param $config_path
     */
    public function __construct($config_path)
    {
        $this->config_path = $config_path;
    }

    /**
     * @return static
     */
    public static function make(): self {
        return new self(getenv('SIMPLESAMLPHP_METADATA_STORAGE_DIR') ?? '/tmp');
    }

    /**
     * @return string
     */
    protected function get_config_file_path(): string
    {
        return rtrim($this->config_path, '/') . '/' . self::CONFIG_FILE;
    }

    /**
     * @param string $metadata_file_hash
     * @return string
     */
    protected function get_metadata_file_path(string $metadata_file_hash): string {
        return rtrim($this->config_path, '/') . '/' . $metadata_file_hash . '.xml';
    }

    /**
     * @param $hash
     * @return string|null
     */
    public function get_metadata_file($hash, bool $raw = false): ?string {
        $path = $raw ? $hash : $this->get_metadata_file_path($hash);

        return file_get_contents($path) ?? null;
    }

    /**
     * @return array|null
     */
    public function get_sp_list(): ?array
    {
        if (!$this->sp_list) {
            if (!file_exists($this->get_config_file_path())) {
                return [];
            }

            $this->sp_list = json_decode(file_get_contents($this->get_config_file_path()), true) ?? [];
        }

        return $this->sp_list;
    }

    /**
     * @param string $url
     * @return string
     */
    protected function url_to_hash(string $url): string {
        return sha1($url);
    }

    protected function save(): void {
        file_put_contents($this->get_config_file_path(), json_encode($this->sp_list));
    }

    /**
     * @param string $metadata_file_hash
     * @param array $content
     * @return void
     */
    protected function save_metadata_file(string $metadata_file_hash, array $content): void {
        $path = $this->get_metadata_file_path($metadata_file_hash);
        file_put_contents($path, json_encode($content));
    }

    /**
     * Save the remote metadata file in a tmp location.
     * The temp location is returned on success
     *
     * @param string $url
     * @return string|null
     */
    protected function save_remote_file(string $url): ?string {
        $ch = curl_init($url);
        $key = $this->url_to_hash($url . '_tmp');
        $tmp_path = $this->get_metadata_file_path($key);
        $fp = fopen($tmp_path, 'w');

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_exec($ch);

        if (curl_error($ch)) {
            // Silently continue
            curl_close($ch);
            fclose($fp);
            return null;
        }

        curl_close($ch);
        fclose($fp);
        return $tmp_path;
    }

    /**
     * @param string $url
     * @return bool
     * @throws Exception
     */
    public function add_url(string $url): bool {

        // Download the remote file, parse it back and extract out each entity.
        $metadata_file = $this->save_remote_file($url);
        if (empty($metadata_file)) {
            throw new Exception('Could not download the metadata file');
        }

        // Load the sp list to memory
        $this->get_sp_list();

        // Make a key - we use this to group entities under this URL (also URLs don't make great array keys)
        $key = $this->url_to_hash($url);

        // If the entry already exists, then go delete everything inside
        $entry = $this->sp_list[$key] ?? [];
        if (!empty($entry) && isset($entry['entities'])) {
            foreach ($entry['entities'] as $data) {
                @unlink($this->get_metadata_file_path($data['file']));
            }
        }

        $entry['entities'] = [];
        $entry['url'] = $url;
        $entry['fetched'] = time();

        // Now parse back our tmp file and add the entities
        $xml = $this->get_metadata_file($metadata_file, true);
        if (empty ($xml)) {
            $this->save();
            throw new \Exception('There was no content in the URL provided.');
        }
        @unlink($metadata_file);

        try {
            (new Utils\XML())->checkSAMLMessage($xml, 'saml-meta');
            $entities = SAMLParser::parseDescriptorsString($xml);
        } catch (\Exception $ex) {
            $entities = [];
        }

        foreach ($entities as $entity) {
            $data = $entity->getMetadata20SP();
            $hash = $this->url_to_hash($url.$entity->getEntityId());
            $this->save_metadata_file($hash, $data);

            $name = $data['name'] ?? $entity->getEntityId();
            if (is_array($name)) {
                $name = current($name);
            }

            $entry['entities'][] = [
                'entity_id' => $entity->getEntityId(),
                'name' => $name,
                'file' => $hash,
            ];
        }

        $this->sp_list[$key] = $entry;
        $this->save();
        return true;
    }

    /**
     * @param string $key
     * @return bool
     * @throws Exception
     */
    public function refresh_entity(string $key): bool {
        $this->get_sp_list();

        if (!isset($this->sp_list[$key])) {
            return false;
        }

        $entry = $this->sp_list[$key];
        $url = $entry['url'];

        return $this->add_url($url);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete_entity(string $key): bool {
        $this->get_sp_list();

        if (!isset($this->sp_list[$key])) {
            return false;
        }

        $entry = $this->sp_list[$key];
        $url = $entry['url'];

        // Go through and delete each metadata file
        foreach ($entry['entities'] as $entity) {
            @unlink($this->get_metadata_file_path($entity['file']));
        }

        unset($this->sp_list[$key]);
        $this->save();
        $this->get_sp_list();

        return !isset($this->sp_list[$key]);
    }
}