<?php
interface GoogleCloudWrapperInterface {
    public function getClient();
    public function getService(Google_Client $client);
}

class GoogleCloudAuthWrapper implements GoogleCloudWrapperInterface {

    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';
    const SCOPE_READ = 'https://www.googleapis.com/auth/devstorage.read_only';
    const SCOPE_READWRITE = 'https://www.googleapis.com/auth/devstorage.read_write';
    const SCOPE_FULLCONTROL = 'https://www.googleapis.com/auth/devstorage.full_control';

    var $CI;
    var $config;
    
    public function __construct() {
        $this->CI =& get_instance();
        $this->config = config_item('gcloud');
    }
    
    public function getClient() {
        $client = new Google_Client();
        //$gcloudConfigFileURI = config_item('gcloud')['json'];
        $gcloudConfigFileURI = $this->config['json'];
        # print_r($gcloudConfigFileURI);
        # die();
        $configJSON = json_decode(file_get_contents($gcloudConfigFileURI));
        $scopes = [ self::SCOPE_READWRITE ];
        $client->setApplicationName($configJSON->project_id);
        // get p12 certificate
        $keyP12 = file_get_contents($this->config['p12_cert']);
        $jwtCreds = new Google_Auth_AssertionCredentials($configJSON->client_email, $scopes, $keyP12);
        $client->setAssertionCredentials($jwtCreds); 
        if($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion($jwtCreds);
        }
        // client authenticated!
        return $client;
    }

    public function getService(\Google_Client $client) {
        return new Google_Service_Oauth2($client);
    }

}
