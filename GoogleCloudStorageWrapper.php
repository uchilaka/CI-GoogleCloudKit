<?php
require_once makepath(__DIR__, 'GoogleCloudAuthWrapper.php');

class GoogleCloudStorageWrapper extends GoogleCloudAuthWrapper {
    
    var $bucketName;
    
    const SCOPE_READ = 'https://www.googleapis.com/auth/devstorage.read_only';
    const SCOPE_READWRITE = 'https://www.googleapis.com/auth/devstorage.read_write';
    const SCOPE_FULLCONTROL = 'https://www.googleapis.com/auth/devstorage.full_control';
    
    public function __construct( $config=null ) {
        parent::__construct($config);
        // load directory library
        $this->CI->load->library('dir');
    }
    
    function setBucket($bucketName) {
        $this->bucketName = $bucketName;
    }
    
    function getScopes() {
        return array_unique(array_merge(parent::getScopes(), [ self::SCOPE_READWRITE ]));
    }
    
    function getBucketURI( $method = self::HTTP_GET ) {
        switch($method) {
            case self::HTTP_POST:
                return "https://www.googleapis.com/upload/storage/v1/b/{$this->bucketName}/o";
                
            default:
                return "https://storage.googleapis.com/{$this->bucketName}/";
        }
    }
    
    function getRemoteFile( $file ) {
        $context = stream_context_create([
            'http'=>[
                'method'=>'GET'
            ]
        ]);
        $content = file_get_contents($file, null, $context);
        // save file locally
        $this->CI->load->helper('string');
        $ext = Dir::file_type($file);
        $fn = random_string('alnum',8) . ".{$ext}";
        $ffn=FCPATH . 'temp' . DIRECTORY_SEPARATOR . $fn;
        file_put_contents($ffn,$content);
        if(!file_exists($ffn)) {
            throw new Exception("Failed to download source file from remote location");
        }
        return $ffn;
    }
    
    public function getService(Google_Client $client) {
        // initialize storage service
        return new Google_Service_Storage($client);        
    }
    
    public function upload( $file, $dir="" ) {
        // default bucket
        if(empty($this->bucketName)) {
            $this->setBucket('larcity-io-public');
        }
        $client = $this->getClient();
        //$service = $this->getService($client);
        $credentialsString = $client->getAccessToken();
        $credentials = json_decode($credentialsString);
        $accessToken = $credentials->access_token;
        // parse file
        if(preg_match("/^https?\:\/\//", $file)) {
            $file = $this->getRemoteFile($file);
        }         
        // fix dir path
        if(!preg_match("/\/$/", $dir)) {
            $dir .= "/";
        }
        $content = file_get_contents($file);
        $get_params = [
            'uploadType'=>'media',
            'name'=>$dir.Dir::file_name($file),
        ];
        $headers = [
            'Content-Type: ' . Dir::mime_type($file),
            'Content-Length: ' . strlen($content),
            'Authorization: OAuth ' . $accessToken,
            'x-goog-api-version: 2',
            'x-goog-acl: public-read'
        ];
        $url = $this->getBucketURI(self::HTTP_POST) . '?' . http_build_query($get_params);
        $options = [
            'http'=>[
                'method'=>'POST',
                'header'=>implode("\r\n", $headers),
                'content'=>$content
            ]
        ];
        $context = stream_context_create($options);
        $RAW = file_get_contents($url, false, $context);
        $output = json_decode($RAW);
        if($output->selfLink) {
            $output->publicLink = $this->getBucketURI(self::HTTP_GET) . $dir . Dir::file_name($file);
        }
        return empty($output) ? $RAW : $output;        
    }
        
}