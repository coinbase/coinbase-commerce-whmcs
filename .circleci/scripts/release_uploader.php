<?php

require_once __DIR__ . '/lib/HttpClient.php';

class AssetUploader
{
    const GITHUB_API_REPOS = 'https://api.github.com/repos';

    private $pluginVersion;

    private $repo;

    private $githubUser;

    public function __construct($file, $token, $repo, $githubUser)
    {
        if (empty($file)) {
            throw new Exception("File not provided");
        }

        if (empty($token)) {
            throw new Exception("Token not found");
        }

        $config = parse_ini_file(dirname(__DIR__) . '/params.ini');
        $this->pluginVersion = $config['version'];

        if (!isset($this->pluginVersion)) {
            throw new Exception("Please set plugin version");
        }

        if (empty($config['repo'])) {
            throw new Exception("Please set repository");
        }

        if (empty($config['github_user'])) {
            throw new Exception("Please set github user");
        }

        $this->headers = [
            sprintf('Authorization: token %s', $token),
            'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)'
        ];

        if (!file_exists($file)) {
            throw new Exception(sprintf("Not found file for uploading. Provided filepath: %s", $file));
        }

        $this->file = $file;
        $this->client = HttpClient::getInstance();
        $this->repo = !empty($repo) ? $repo : $config['repo'];
        $this->githubUser = !empty($githubUser) ? $githubUser : $config['github_user'];
    }

    public function run()
    {
        $release = $this->createRelease();
        $this->uploadAssets($release);
    }

    function createRelease() {
        $apiUrl = self::GITHUB_API_REPOS . DIRECTORY_SEPARATOR . $this->githubUser . DIRECTORY_SEPARATOR . $this->repo . DIRECTORY_SEPARATOR . 'releases';

        $response = $this->client->request('GET', $apiUrl, [], '', $this->headers);
        // Check is release with current plugin version == tag name exists
        foreach ($response->bodyArray as $release) {
            if ($release['tag_name'] === $this->pluginVersion) {
                return $release;
            }
        }

        // Create release
        $body = [
            'tag_name' => $this->pluginVersion,
            'name' => ''
        ];

        $response = $this->client->request('POST', $apiUrl, [], json_encode($body), $this->headers);

        return $response->bodyArray;
    }

    function uploadAssets($release) {

        $path_parts = pathinfo($this->file);
        $fileLabel = $this->repo . '_' . str_replace('.', '_', $this->pluginVersion) . '.' . $path_parts['extension'];
        $fileName = $this->repo . '_' . str_replace('.', '_', $this->pluginVersion) . '_' . time()  . '.' . $path_parts['extension'];

        // Check is previous file was uploaded
        if (isset($release['assets'])) {
            foreach ($release['assets'] as $asset) {

                if ($asset['label'] == $fileLabel) {
                    // Delete asset
                    $this->client->request('DELETE', $asset['url'], [], '', $this->headers);

                    // Update release body
                    $body = [
                        'body' => null
                    ];

                    $headers = array_merge($this->headers, ['Content-Type: application/json']);

                    $this->client->request('PATCH', $release['url'], [], json_encode($body), $headers);
                }
            }
        }

        $headers = array_merge($this->headers, ['Content-Type: multipart/form-data']);
        $response = $this->client->request(
            'POST',
            str_replace('{?name,label}', '', $release['upload_url']),
            ['name' => $fileName, 'label' => $fileLabel],
            file_get_contents($this->file),
            $headers
        );


        if ($response->code == '201') {
            echo sprintf('Successfully uploaded file. File path: %s', $response->bodyArray["url"]) . PHP_EOL;

            // Update release body
            $body = [
                'body' => sprintf('MD5 Hash of file **%s**: %s', $fileLabel, md5_file($this->file))
            ];

            $headers = array_merge($this->headers, ['Content-Type: application/json']);
            $this->client->request(
                'PATCH',
                $release['url'],
                [],
                json_encode($body),
                $headers
            );
        }
    }
}

$longopts  = array(
    "token:",
    "file:",
    "repo::",
    "github_user::"
);

$options = getopt('', $longopts);

$file = isset($options['file']) ? $options['file']: '';
$token = isset($options['token']) ? $options['token']: '';
$repo = isset($options['repo']) ? $options['repo']: '';
$githubUser = isset($options['github_user']) ? $options['github_user']: '';

$handler = new AssetUploader($file, $token, $repo, $githubUser);
$handler->run();
