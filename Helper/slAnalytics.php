<?php
/**
 * Synccatalog Analytics helper
 */

namespace Saleslayer\Synccatalog\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Saleslayer\Synccatalog\Helper\slDebuger;

class slAnalytics extends AbstractHelper
{
    private $SL_API_URL = 'https://api.saleslayer.com/?s=conn_plug_analytics';

    private $slDebuger;
    protected $scopeConfig;
    private $analyticsData = [];

    /**
     * Analytics constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param slDebuger $slDebuger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        slDebuger $slDebuger,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->slDebuger = $slDebuger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Function to get Analytics public key
     *
     * @return string|null Analytics public key
     */
    private function getAnalyticsPublicKey(): ?string
    {
        $analyticsPublicKey = $this->scopeConfig->getValue('synccatalog/general/analytics_public_key');

        return $analyticsPublicKey ? base64_decode($analyticsPublicKey) : null;
    }

    /**
     * Function to load analytics data
     *
     * @param array $analyticsData Analytics data
     * @return bool True if data is loaded, false otherwise
     */
    public function loadAnalyticsData(array $analyticsData): bool
    {
        if ($this->validateData($analyticsData)) {
            $this->analyticsData = $analyticsData;
            return true;
        }

        return false;
    }

    /**
     * Function to validate data
     *
     * @param array $data Data to validate indexes
     * @return bool True if data is valid, false otherwise
     */
    private function validateData(array $data): bool
    {
        $expectedKeys = [
            'conn_code',
            'comp_id',
            'secret_key',
            'conn_type',
            'last_update',
            'api_item_count',
            'ecommerce_version',
            'plugin_version'
        ];
    
        if (isset($data['plugin_config']['all_analytics_data']) && $data['plugin_config']['all_analytics_data'] == 0){
            if (($keyToRemove = array_search('ecommerce_version', $expectedKeys)) !== false) {
                unset($expectedKeys[$keyToRemove]);
            }
        }

        foreach ($expectedKeys as $key) {
            if (empty($data[$key])) {
                $this->slDebuger->debug('## Error. Analytics data incomplete. Missing index: '.print_r($key, true));
                return false;
            }
        }

        return true;
    }

    /**
     * Function to send analytics calls through cURL
     *
     * @return bool True if cURL response is valid, false otherwise
     */
    public function sendAnalyticsData(): bool
    {
        $encryptedPackage = $this->createEncryptedJsonPackage();
        if (!$encryptedPackage) {
            return false;
        }

        $ch = curl_init($this->SL_API_URL);

        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 1800,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['encryptedPackage' => $encryptedPackage],
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            // Connection error or another cURL error
            $this->slDebuger->debug('## Error. Analytics error connection: '.curl_error($ch));
            curl_close($ch);
            return false;
        }

        // Obtain the answer HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code >= 400) {
            // If the HTTP code is 400 or higher, we consider it an error
            $this->slDebuger->debug('## Error. HTTP Error '.$http_code.'. Response: '.print_r($response, true));
            curl_close($ch);
            return false;
        }

        if ($http_code == 200) {
            // If the HTTP code is 200, we print the response
            $this->slDebuger->debug('Analytics data stored: '.$response);
            curl_close($ch);
            return true;

        }

        curl_close($ch);

        return true;
    }

    /**
     * Function to encrypt analytics data into a JSON package
     *
     * @return string|false Encrypted JSON package, or false on failure
     */
    private function createEncryptedJsonPackage()
    {
        $publicKey = $this->getAnalyticsPublicKey();
        if (!$publicKey) {
            $this->slDebuger->debug('## Error. Invalid public key.');
            return false;
        }

        $encodedData = json_encode($this->analyticsData);

        // Generate an AES key for this operation
        $aesKey = openssl_random_pseudo_bytes(32); // 256 bits for AES-256
       
        // Encrypt data with AES
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encryptedData = openssl_encrypt($encodedData, 'AES-256-CBC', $aesKey, 0, $iv);

        // Encrypt AES key with RSA public key
        if (!openssl_public_encrypt($aesKey, $encryptedCEKey, $publicKey)) {
            $this->slDebuger->debug('## Error. RSA encryption failed.');
            return false;
        }

        // Generate an HMAC to ensure the message's integrity
        $hmac = hash_hmac('sha256', $encryptedData, $aesKey, true);

        $encryptedPackage = [
            'iv' => base64_encode($iv),
            'encryptedData' => base64_encode($encryptedData),
            'encryptedCEKey' => base64_encode($encryptedCEKey),
            'hmac' => base64_encode($hmac),
        ];

        $encryptedJsonPackage = base64_encode(json_encode($encryptedPackage));

        return $encryptedJsonPackage;
    }
}
