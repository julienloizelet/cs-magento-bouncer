<?php declare(strict_types=1);
/**
 * CrowdSec_Bouncer Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT LICENSE
 * that is bundled with this package in the file LICENSE
 *
 * @category   CrowdSec
 * @package    CrowdSec_Bouncer
 * @copyright  Copyright (c)  2021+ CrowdSec
 * @author     CrowdSec team
 * @see        https://crowdsec.net CrowdSec Official Website
 * @license    MIT LICENSE
 *
 */

/**
 *
 * @category CrowdSec
 * @package  CrowdSec_Bouncer
 * @module   Bouncer
 * @author   CrowdSec team
 *
 */

namespace CrowdSec\Bouncer\Controller\Adminhtml\System\Config\Connection;

use CrowdSec\Bouncer\Controller\Adminhtml\System\Config\Action;
use Exception;
use LogicException;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use CrowdSec\Bouncer\Registry\CurrentBouncer as RegistryBouncer;
use CrowdSec\Bouncer\Helper\Data as Helper;
use CrowdSec\Bouncer\Constants;

class Ping extends Action implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var RegistryBouncer
     */
    protected $registryBouncer;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param RegistryBouncer $registryBouncer
     * @param Helper $helper
     */
    public function __construct(
        Context        $context,
        JsonFactory    $resultJsonFactory,
        RegistryBouncer $registryBouncer,
        Helper         $helper
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->registryBouncer = $registryBouncer;
        $this->helper = $helper;
    }

    /**
     * Test connection
     *
     * @return Json
     * @throws LogicException
     */
    public function execute(): Json
    {
        $useCurl = "";
        $tlsVerifyPeer = "";
        $authType ="";
        try {
            $baseUri = $this->getRequest()->getParam('api_url');
            $authType = $this->getRequest()->getParam('auth_type');
            $tlsCert = ($authType === Constants::AUTH_TLS) ? $this->getRequest()->getParam('tls_cert_path', "") : "";
            $tlsKey = ($authType === Constants::AUTH_TLS) ? $this->getRequest()->getParam('tls_key_path', "") : "";
            $tlsVerifyPeer = (bool)$this->getRequest()->getParam('tls_verify_peer', false);
            $tlsCaCert =
                ($authType === Constants::AUTH_TLS) ? $this->getRequest()->getParam('tls_ca_cert_path', "") : "";
            $userAgent = Constants::BASE_USER_AGENT;
            $apiKey = ($authType === Constants::AUTH_KEY) ? $this->getRequest()->getParam('bouncer_key') : "";
            $useCurl = (bool)$this->getRequest()->getParam('use_curl', false);
            $configs = $this->helper->getBouncerConfigs();
            $currentConfigs = [
                'api_url' => $baseUri,
                'auth_type' => $authType,
                'tls_cert_path' => $this->helper->getVarFullPath($tlsCert),
                'tls_key_path' => $this->helper->getVarFullPath($tlsKey),
                'tls_verify_peer' => $tlsVerifyPeer,
                'tls_ca_cert_path' => $this->helper->getVarFullPath($tlsCaCert),
                'api_user_agent' => $userAgent,
                'api_key' => $apiKey,
                'use_curl' => $useCurl
            ];

            $useCurl = $useCurl ? __('true') : __('false');
            $tlsVerifyPeer = $tlsVerifyPeer ? __('true') : __('false');
            $finalConfigs = array_merge($configs, $currentConfigs);
            $bouncer = $this->registryBouncer->create([
                'helper' => $this->helper,
                'configs' => $finalConfigs
            ]);
            $restClient = $bouncer->getRemediationEngine()->getClient();
            $this->helper->ping($restClient);
            $result = 1;
            $message = __('Connection test result: success.');
        } catch (Exception $e) {
            $this->helper->error('Error while testing connection', [
                'type' => 'M2_EXCEPTION_WHILE_TESTING_CONNECTION',
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $result = false;
            $message = __('Technical error while testing connection: ' . $e->getMessage());
        }

        $resultJson = $this->resultJsonFactory->create();

        $suffixMessage = ($authType === Constants::AUTH_TLS) ? '<br><br>' . __(
            'Auth type: TLS <br>Url: %1<br>Cert: %2<br>Key: %3<br>Verify peer: %4<br>CA cert: %5<br>Use cURL: %6',
            $baseUri ?? "",
            $tlsCert ?? "",
            $tlsKey ?? "",
            $tlsVerifyPeer,
            $tlsCaCert ?? "",
            $useCurl
        ) : '<br><br>' . __(
            'Auth type: Api key <br>Url: %1 <br>Api key: %2<br>Use cURL: %3',
            $baseUri ?? "",
            $apiKey ?? "",
            $useCurl
        );

        return $resultJson->setData([
            'connection' => $result,
            'message' => $message . $suffixMessage,
        ]);
    }
}
