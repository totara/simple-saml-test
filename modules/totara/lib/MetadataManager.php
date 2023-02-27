<?php

namespace SimpleSAML\Module\totara;

/**
 * Helper class handling management of the persisted SP metadata files.
 */
class MetadataManager
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
     * @param string $url
     * @return string
     */
    protected function get_metadata_file_path(string $url): string {
        return rtrim($this->config_path, '/') . '/' . $this->url_to_hash($url) . '.xml';
    }

    /**
     * @param $hash
     * @return string|null
     */
    public function get_metadata_file($hash): ?string {
        $path = rtrim($this->config_path, '/') . '/' . $hash . '.xml';

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
     * @param string $url
     * @param string $filename
     * @return bool
     */
    protected function save_remote_file(string $url): bool {
        $ch = curl_init($url);
        $fp = fopen($this->get_metadata_file_path($url), 'w');

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
        return true;
    }

    /**
     * @param string $url
     * @return void
     */
    public function add_url(string $url) {
        $this->get_sp_list();
        $key = $this->url_to_hash($url);

        $this->sp_list[$key] = [
            'url' => $url,
            'fetched' => null,
        ];

        $this->save();

        // Fetch the XML
        if ($this->save_remote_file($url)) {
            $this->sp_list[$key]['fetched'] = time();
            $this->save();
        }
    }

    /**
     * @param string $url
     * @return bool
     */
    public function refresh_entity(string $key): bool {
        $this->get_sp_list();

        if (!isset($this->sp_list[$key])) {
            return false;
        }

        $entry = $this->sp_list[$key];
        $url = $entry['url'];

        if ($this->save_remote_file($url)) {
            $this->sp_list[$key]['fetched'] = time();
            $this->save();
            return true;
        }

        $this->sp_list[$key]['fetched'] = null;
        $this->save();
        return false;
    }

    /**
     * @param string $url
     * @return bool
     */
    public function delete_entity(string $key): bool {
        $this->get_sp_list();

        if (!isset($this->sp_list[$key])) {
            return false;
        }

        $entry = $this->sp_list[$key];
        $url = $entry['url'];
        unset($this->sp_list[$key]);

        $this->save();

        @unlink($this->get_metadata_file_path($url));

        $this->get_sp_list();

        return !isset($this->sp_list[$key]);
    }
}