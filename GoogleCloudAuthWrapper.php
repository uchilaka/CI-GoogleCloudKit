<?php
interface GoogleCloudWrapperInterface {
    public function getClient();
    public function getService(Google_Client $client);
}

/** @TODO 
 * Make the following directories writeable by the PHP process: 
 * /third_party/uchiaka/ci-gcloudkit/bin 
 * /third_party/uchiaka/ci-gcloudkit/certs
**/
class GoogleCloudAuthWrapper implements GoogleCloudWrapperInterface {

    const HTTP_GET = 'GET';
    const HTTP_POST = 'POST';
    const SCOPE_READ = 'https://www.googleapis.com/auth/devstorage.read_only';
    const SCOPE_READWRITE = 'https://www.googleapis.com/auth/devstorage.read_write';
    const SCOPE_FULLCONTROL = 'https://www.googleapis.com/auth/devstorage.full_control';

    var $CI;
    var $config;
    var $workingDir;
    var $certDir;
    
    public function __construct( $config=null ) {
        $this->CI =& get_instance();
        $this->config = config_item('gcloud');
        $this->workingDir = makepath(APPPATH, 'third_party', 'uchilaka', 'ci-gcloudkit');
        $this->certDir = makepath($this->workingDir, 'certs');
        // include json file in configuration
        if(!array_key_exists('json_file', $config)) {
            throw new Exception('GoogleCloudAuthWrapper MUST be initialized with a configuration arguments including the key for a Google Cloud service account `json_file`', 400);
        }
        $json = json_decode(file_get_contents($config['json_file']), true);
        // check for RSA certificate file
        $rsaFileURI = makepath($this->certDir, 'RSA.crt');
        if(!is_file($rsaFileURI)) {
            file_put_contents($rsaFileURI, $json['private_key']);
        }
        $p12FileURI = makepath($this->certDir, 'gcloud.p12');
        if(!is_file($p12FileURI)) {
            throw new Exception('Please follow the instructions for generating the .p12 certificate from your RSA private key (included in your google cloud json service account file)', 400);
        }
        // expand config to include contents of service account json
        $this->config['json_file'] = $config['json_file'];
    }
    
    public function getClient() {
        $client = new Google_Client();
        $gcloudConfigFileURI = $this->config['json_file'];
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
