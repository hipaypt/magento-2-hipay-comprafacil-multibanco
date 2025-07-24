<?php
namespace Hipay\HipayMultibancoGateway\Controller\Notify;

use Magento\Framework\App\Action\Action as AppAction;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http as HttpRequest;
use Psr\Log\LoggerInterface;

class Index extends AppAction
{

	const HIPAY_PRODUCTION_ENVIRONMENT_10241 = "https://comprafacil1.hipay.pt/webservice/comprafacilWS.asmx?wsdl";
	const HIPAY_PRODUCTION_ENVIRONMENT_11249 = "https://comprafacil2.hipay.pt/webservice/comprafacilWS.asmx?wsdl";
	const HIPAY_SANDBOX_ENVIRONMENT_10241 = "https://comprafacil1.hipay.pt/webservice-test/comprafacilWS.asmx?wsdl";
	const HIPAY_SANDBOX_ENVIRONMENT_11249 = "https://comprafacil2.hipay.pt/webservice-test/comprafacilWS.asmx?wsdl";

    protected $_messageManager;
    protected $_context;
    protected $_order;
    protected $_sandbox;
    protected $_credentials;
 	protected $_payment;
 	protected $_entity;
 	protected $_logger;
 	
    public function __construct(\Magento\Framework\App\Action\Context $context, LoggerInterface $logger) {
		
        parent::__construct($context);
        $this->_messageManager = $context->getMessageManager();
        $this->_logger = $logger;
    }
    
    public function execute()
    {
		try {
		
			header('HTTP/1.1 402 Payment Required');
			if(!isset($_GET["order"])) {
				return;
			};
			
			$idformerchant = $_GET["order"];
			$reference = $_GET["ref"];
			$entity = $_GET["ent"]; 

			$this->_order = $this->_objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($idformerchant);
			$this->_payment = $this->_order->getPayment();
		
			$storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
			$this->_sandbox 		= $this->_objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/hipay_multibanco_gateway/sandbox',$storeScope);
			$this->_entity	 		= $this->_objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/hipay_multibanco_gateway/payment_entity',$storeScope);
			if ($this->_sandbox)
				$this->_credentials		= $this->_objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/hipay_multibanco_gateway/api_sandbox' ,$storeScope);
			else
				$this->_credentials		= $this->_objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/hipay_multibanco_gateway/api_production',$storeScope);
			
			$hipayTransaction = $this->getTransactionStatus($reference);
			$dataCheck = $this->checkTransactionData( $entity, $reference );
			
			if ($hipayTransaction && $dataCheck){

				$this->_logger->info("Multibanco Transaction identified for capture" . $idformerchant);
				echo "Multibanco Transaction identified for capture " . $idformerchant . PHP_EOL;
				if ($this->_order->getState() != \Magento\Sales\Model\Order::STATE_CANCELED && $this->_order->getState() != \Magento\Sales\Model\Order::STATE_PROCESSING && $this->_order->getState() != \Magento\Sales\Model\Order::STATE_COMPLETE){
					$this->_order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)->setStatus("processing");
					$comment = "Captured, " . date('Y-m-d H:i:s');
					$this->_order->addStatusHistoryComment($comment)->setIsCustomerNotified(true)->setEntityName('order');
					$this->_order->save();
				}	

				if ($this->_order->getState() == \Magento\Sales\Model\Order::STATE_PROCESSING || $this->_order->getState() == \Magento\Sales\Model\Order::STATE_COMPLETE){
					$this->_logger->info("Multibanco Transaction captured. Status updated for " . $idformerchant);
					header('HTTP/1.1 200 OK');
					echo "Transaction captured. Status updated for Multibanco transaction $idformerchant." . PHP_EOL;
				} else {
					header('HTTP/1.1 500 Internal Server Error');
					$this->_logger->error("An error occurred while processing Multibanco capture request: " . $idformerchant);
					echo "An error occurred while updating the Multibanco transaction: " . $idformerchant;
					exit;
				}			
				
			} else {
				header('HTTP/1.1 500 Internal Server Error');
				$this->_logger->error("Error processing Multibanco capture, data does not match: " . $idformerchant);
				echo "Error processing Multibanco capture, data does not match: " . $idformerchant;
				exit;
			}
			
			
		} catch (\Exception $e){
		
			header('HTTP/1.1 500 Internal Server Error');
			$this->_logger->error("An error occurred while processing a notification request: " . $e->getMessage());
			echo "An error occurred while processing a notification request: " . $e->getMessage();
			exit;			
		}
	}
	
	
	protected function getTransactionStatus($reference){
		
		switch($this->_entity){
			case "11249":
				if ($this->_sandbox )
					$ws_url= self::HIPAY_SANDBOX_ENVIRONMENT_11249;
				else
					$ws_url = self::HIPAY_PRODUCTION_ENVIRONMENT_11249;
				break;
				
			case "10241":
				if ($this->_sandbox )
					$ws_url = self::HIPAY_SANDBOX_ENVIRONMENT_10241;
				else
					$ws_url = self::HIPAY_PRODUCTION_ENVIRONMENT_10241;
				break;
		}
		

		$client = new \SoapClient($ws_url);
		$parameters = new \stdClass(); 

		$parameters = array(
			"reference" => $reference,
			"username" => $this->_credentials["merchant_api_login"],
			"password" => $this->_credentials["merchant_api_password"]
			);

		$res = $client->getInfoReference($parameters);
		if ($res->getInfoReferenceResult)
		{
			$paid = $res->paid;
			if ($paid)
			{
				echo "Reference is checked as paid";
				return true;

			} else
			{
				echo "Reference check as NOT paid";
				return false;
			}
		}
		else
		{
			return false;
		}

		return false;			
		
	}	


    protected function checkTransactionData($entity, $reference)
    {

		if ( $this->_payment->getMethod() != "hipay_multibanco_gateway") {
			$this->_logger->error("Payment method does not match: hipay_multibanco_gateway");	
			return false;
		}		

		if ( $this->_payment->getAdditionalInformation('MB_Entity') != $entity || $this->_payment->getAdditionalInformation('MB_Reference') != $reference ){
			$this->_logger->error("Payment data like Multibanco entity and reference do not match: hipay_multibanco_gateway");	
			return false;
		}
		return true;

    }


}
	
