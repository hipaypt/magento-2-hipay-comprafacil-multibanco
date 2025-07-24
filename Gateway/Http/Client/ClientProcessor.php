<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Hipay\HipayMultibancoGateway\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;

class ClientProcessor implements ClientInterface
{
    const SUCCESS = 1;
    const FAILURE = 0;

	const HIPAY_PRODUCTION_ENVIRONMENT_10241 = "https://comprafacil1.hipay.pt/webservice/comprafacilWS.asmx?wsdl";
	const HIPAY_PRODUCTION_ENVIRONMENT_11249 = "https://comprafacil2.hipay.pt/webservice/comprafacilWS.asmx?wsdl";
	const HIPAY_SANDBOX_ENVIRONMENT_10241 = "https://comprafacil1.hipay.pt/webservice-test/comprafacilWS.asmx?wsdl";
	const HIPAY_SANDBOX_ENVIRONMENT_11249 = "https://comprafacil2.hipay.pt/webservice-test/comprafacilWS.asmx?wsdl";

	private $sandbox;
	private $entity;
	private $ws_url;
	private $expiry;
	
    private $results = [
        self::SUCCESS,
        self::FAILURE
    ];

    private $logger;
	private $soapClientFactory;
	private $storeManager;
	private $urlBuilder;
	
    /**
     * @param Logger $logger
     */
    public function __construct( Logger $logger, \Magento\Framework\Webapi\Soap\ClientFactory $soapClientFactory, \Magento\Store\Model\StoreManagerInterface $storeManager, \Magento\Framework\UrlInterface $urlBuilder ) {
        $this->logger 				= $logger;
        $this->soapClientFactory 	= $soapClientFactory;
        $this->storeManager 		= $storeManager;
        $this->urlBuilder			= $urlBuilder;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject)
    {

		$obj = $transferObject->getBody();
		
		$this->sandbox 	= $obj["SANDBOX"];
		$this->entity 	= $obj["ENTITY"];
		$this->expiry 	= $obj["EXPIRY"];
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
		$store = $objectManager->get('Magento\Framework\Locale\Resolver'); 

		$parameters = array();
		$parameters = array(
			"origin" => $this->urlBuilder->getUrl('hipay_multibanco_gateway/notify/index', ['_secure' => true]) . "?order=" . $obj["INVOICE"],
			"username" => $obj["MERCHANT_CREDENTIALS"]["merchant_api_login"],
			"password" => $obj["MERCHANT_CREDENTIALS"]["merchant_api_password"],
			"amount" => number_format($obj["AMOUNT"],2,".",""),
			"additionalInfo" => "",
			"name" => "",
			"address" => "",
			"postCode" => "",
			"city" => "",
			"NIC" => "",
			"externalReference" => $obj["INVOICE"],
			"contactPhone" => "",
			"email" => $obj["EMAIL"],
			"IDUserBackoffice" => -1,
			"timeLimitDays" => (int)$this->expiry,
			"sendEmailBuyer" => false
		);
	
		$result = $this->_generatePaymentReference($parameters);

		if (!$result->getReferenceMBResult) {		
			if ($obj["DEBUG"])
				$this->logger->debug(
				[
					'result'	 	=> $result,
					'order_params' 	=> $parameters
				]
				);		
			throw new \Exception($result->error);
		}

		$platform = $this->getPlatform();
        $response = [
                'RESULT_CODE' 	=> $result,
                'ENTITY'	 	=> $result->entity,
                'REFERENCE' 	=> $result->reference,
                'AMOUNTOUT' 	=> $result->amountOut,
                'ACCOUNT_TYPE' 	=> $platform,
                'EXPIRY_DATE' 	=> $this->_getExpiryDate(),                
                'TRANSACTION_ID'=> $this->generateTxnId($result->entity.$result->reference.date('YmdHis'))
                
            ];

		if ($obj["DEBUG"])
			$this->logger->debug(
            [
				'order_params' 	=> $parameters,
                'request' 		=> $transferObject->getBody(),
                'response' 		=> $response
            ]
			);

        return $response;
    }

    /**
     * Generates payment url
     *
     * @return array
     */
	private function _generatePaymentReference($parameters) {
 
		$this->ws_url = $this->_getEndpoint();
	
		try {
			$client = $this->soapClientFactory->create($this->ws_url);
			$result = $client->getReferenceMB ($parameters);
			return $result;
	        
		} catch (Exception $e) {
			return $e->getMessage();
		}

	}


    /**
     * Generates expiry date
     *
     * @return string
     */
	private function _getExpiryDate(){

		$date = strtotime("+".$this->expiry." day");
		return date('Y-m-d', $date);
		
	}	
	     
    /**
     * Get endpoint
     *
     * @return string
     */
     
	private function _getEndpoint(){

		switch($this->entity){
			case "11249":
				if ($this->sandbox )
					return self::HIPAY_SANDBOX_ENVIRONMENT_11249;
				else
					return self::HIPAY_PRODUCTION_ENVIRONMENT_11249;
				break;
				
			case "10241":
				if ($this->sandbox )
					return self::HIPAY_SANDBOX_ENVIRONMENT_10241;
				else
					return self::HIPAY_PRODUCTION_ENVIRONMENT_10241;
				break;
		}
	}	
     
    /**
     * @return string
     */
    protected function generateTxnId($source)
    {
        return md5($source);
    }

    /**
     * @return string
     */
    protected function getPlatform()
    {
        if (!$this->sandbox)
			return "PRODUCTION";
		else
			return "SANDBOX";
    }

}
