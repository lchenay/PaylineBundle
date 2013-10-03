<?php

namespace Stloc\Bundle\PaylineBundle;

class PaylineService
{
	protected $options;
	protected $identification;
	
	// kit version
	const KIT_VERSION		= 'kit PHP version 1.3';
	
	// trace log
	var $paylineTrace;
	
	// SOAP URL's
	const PAYLINE_NAMESPACE	= 'http://obj.ws.payline.experian.com';
	const WSDL				= 'Payline.wsdl';
	const PROD_ENDPOINT		= 'https://services.payline.com/V4/services/';
	const HOMO_ENDPOINT		= 'https://homologation.payline.com/V4/services/';
	//const HOMO_ENDPOINT		= 'https://ws.dev.payline.aixlan.local:9364/V4/services/';
	
	const DIRECT_API 		= 'DirectPaymentAPI';
	const EXTENDED_API 		= 'ExtendedAPI';
	const WEB_API 			= 'WebPaymentAPI';
	
	// current endpoint
	private $webServicesEndpoint;
	
	// version of web service
	private $version = '';
	
	// devise used by the customer
	private $media = '';
	
	// SOAP ACTIONS CONSTANTS
	const soap_result = 'result';
	const soap_authorization = 'authorization';
	const soap_card = 'card';
	const soap_order = 'order';
	const soap_orderDetail = 'orderDetail';
	const soap_payment = 'payment';
	const soap_transaction = 'transaction';
	const soap_privateData = 'privateData';
	const soap_buyer = 'buyer';
	const soap_owner = 'owner';
	const soap_address = 'address';
	const soap_capture = 'capture';
	const soap_refund = 'refund';
	const soap_refund_auth = 'refundAuthorization';
	const soap_authentication3DSecure = 'authentication3DSecure';
	const soap_bankAccountData = 'bankAccountData';
	const soap_cheque = 'cheque';
	
	// ARRAY
	public $header_soap;
	public $items;
	public $privates;
	
	// OPTIONS
	public $cancelURL;
	public $securityMode;
	public $notificationURL;
	public $returnURL;
	public $customPaymentTemplateURL;
	public $customPaymentPageCode;
	public $languageCode;
	
	// WALLET
	public $walletIdList;
	
	/**
	 * contructor of PAYLINESDK CLASS
	 **/
	public function __construct($options, $identification) {
		$this->options = $options;
		$this->identification = $identification;
		
		$this->returnURL = $this->getOption('RETURN_URL');
		$this->cancelURL = $this->getOption('CANCEL_URL');
		$this->notificationURL = $this->getOption('NOTIFICATION_URL');

		$this->header_soap = array();
		$this->header_soap['login'] = $this->identification['MERCHANT_ID'];
		$this->header_soap['password'] = $this->identification['ACCESS_KEY'];
		if($this->identification['PROXY_HOST'] != ''){
			$this->header_soap['proxy_host'] = $this->identification['PROXY_HOST'];
			$this->header_soap['proxy_port'] = $this->identification['PROXY_PORT'];
			$this->header_soap['proxy_login'] = $this->identification['PROXY_LOGIN'];
			$this->header_soap['proxy_password'] = $this->identification['PROXY_PASSWORD'];
		}
		if($this->identification['PRODUCTION']){
			$this->webServicesEndpoint = PaylineService::PROD_ENDPOINT;
		}else{
			$this->webServicesEndpoint = PaylineService::HOMO_ENDPOINT;
		}
		$this->header_soap['style'] = SOAP_DOCUMENT;
		$this->header_soap['use'] = SOAP_LITERAL;
		$this->header_soap['version'] = PaylineService::KIT_VERSION;
		$this->items = array();
		$this->privates = array();
		$this->walletIdList = array();
		
		ini_set('user_agent', "PHP\r\nversion: ".PaylineService::KIT_VERSION);
	}
	
	function getOptions() {
		return $this->options;
	}
	
	function getOption($option) {
		return $this->options[$option];
	}
	
	function getIdentification() {
		return $this->identification;
	}
	
	function getKitVersion() {
		return self::KIT_VERSION;
	}	
	
	/**
	 * function payment
	 * @params : $array : array. the array keys are listed in pl_payment CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_payment instance from $array and make SoapVar object for payment.
	 **/
	protected function payment($array) {
		$payment = new pl_payment();
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $payment)&&(strlen($v))){
					$payment->$k = $v;
				}
			}
		}		
		return new \SoapVar($payment, SOAP_ENC_OBJECT, PaylineService::soap_payment, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function order
	 * @params : $array : array. the array keys are listed in pl_order CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_order instance from $array and make SoapVar object for order.
	 **/
	protected function order($array) {
		$order = new pl_order();
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $order)&&(strlen($v))){
					$order->$k = $v;
				}
			}
		}
		$allDetails = array();
		// insert orderDetails
		$order->details = $this->items;
		return new \SoapVar($order, SOAP_ENC_OBJECT, PaylineService::soap_order, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function address
	 * @params : $address : array. the array keys are listed in pl_address CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_address instance from $array and make SoapVar object for address.
	 **/
	protected function address($array) {
		$address = new pl_address();
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $address)&&(strlen($v)))$address->$k = $v;
			}
		}
		return new \SoapVar($address, SOAP_ENC_OBJECT, PaylineService::soap_address, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function buyer
	 * @params : $array : array. the array keys are listed in pl_buyer CLASS.
	 * @params : $shippingAdress : array. the array keys are listed in pl_address CLASS.
	 * @params : $billingAddress : array. the array keys are listed in pl_address CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_buyer instance from $array and $address and make SoapVar object for buyer.
	 **/
	protected function buyer($array,$shippingAdress,$billingAddress) {
		$buyer = new pl_buyer();
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $buyer)&&(strlen($v)))$buyer->$k = $v;
			}
		}
		$buyer->shippingAdress = $this->address($shippingAdress);
		$buyer->billingAddress = $this->address($billingAddress);
		return new \SoapVar($buyer, SOAP_ENC_OBJECT, PaylineService::soap_buyer, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function owner
	 * @params : $array : array. the array keys are listed in pl_buyer CLASS.
	 * @params : $shippingAdress : array. the array keys are listed in pl_address CLASS.
	 * @params : $billingAddress : array. the array keys are listed in pl_address CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_buyer instance from $array and $address and make SoapVar object for buyer.
	 **/
	protected function owner($array,$Address) {
		if($array != null){
			$owner = new pl_owner();
			if($array && is_array($array)){
				foreach($array as $k=>$v){
					if(array_key_exists($k, $owner)&&(strlen($v)))$owner->$k = $v;
				}
			}
			$owner->billingAddress = $this->address($Address);
			return new \SoapVar($owner, SOAP_ENC_OBJECT, PaylineService::soap_owner, PaylineService::PAYLINE_NAMESPACE);
		}else{
			return null;
		}
	}
	
	/**
	 * function contracts
	 * @params : $contracts : array. array of contracts
	 * @return : $contracts : array. the same as params if exist, or an array with default contract defined in
	 * configuration
	 * @description : Add datas to contract array
	 **/
	protected function contracts($contracts) {
		if($contracts && is_array($contracts)){
			return $contracts;
		}
		return array($this->getOption('CONTRACT_NUMBER'));
	}
	
	/**
	 * function secondContracts
	 * @params : $secondContracts : array. array of contracts
	 * @return : $secondContracts : array. the same as params if exist, null otherwise
	 * @description : Add datas to contract array
	 **/
	protected function secondContracts($secondContracts) {
		if($secondContracts && is_array($secondContracts)){
			return $secondContracts;
		}
		return null;
	}
	
	/**
	 * function authentification 3Dsecure
	 * @params : $array : array. the array keys are listed in pl_card CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_authentication3DSecure instance from $array and make SoapVar object for authentication3DSecure.
	 **/
	protected function authentication3DSecure($array) {
		$authentication3DSecure = new pl_authentication3DSecure($array);
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $authentication3DSecure)&&(strlen($v))){
					$authentication3DSecure->$k = $v;
				}
			}
		}
		return new \SoapVar($authentication3DSecure, SOAP_ENC_OBJECT, PaylineService::soap_authentication3DSecure, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function authorization
	 * @params : $array : array. the array keys are listed in pl_card CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_authentication3DSecure instance from $array and make SoapVar object for authentication3DSecure.
	 **/
	protected function authorization($array) {
		$authorization = new pl_authorization($array);
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $authorization)&&(strlen($v))){
					$authorization->$k = $v;
				}
			}
		}
		return new \SoapVar($authorization, SOAP_ENC_OBJECT, PaylineService::soap_authorization, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function card
	 * @params : $array : array. the array keys are listed in pl_card CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_card instance from $array and make SoapVar object for card.
	 **/
	protected function card($array) {
		$card = new pl_card($array['type']);
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $card)&&(strlen($v))){
					$card->$k = $v;
				}
			}
		}
		return new \SoapVar($card, SOAP_ENC_OBJECT, PaylineService::soap_card, PaylineService::PAYLINE_NAMESPACE);
	}
	
	
	
	/**
	 * function bankAccountData
	 * @params : $array : array. the array keys are listed in pl_bankAccountData CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_bankAccountData instance from $array and make SoapVar object for bankAccountData.
	 **/
	protected function bankAccountData($array) {
		$bankAccountData = new pl_bankAccountData($array);
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $bankAccountData)&&(strlen($v))){
					$bankAccountData->$k = $v;
				}
			}
		}
		return new \SoapVar(null, SOAP_ENC_OBJECT, PaylineService::soap_bankAccountData, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function cheque
	 * @params : $array : array. the array keys are listed in pl_cheque CLASS.
	 * @return : SoapVar : object
	 * @description : build pl_authentication3DSecure instance from $array and make SoapVar object for cheque.
	 **/
	protected function cheque($array) {
		$cheque = new pl_cheque($array);
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $cheque)&&(strlen($v))){
					$cheque->$k = $v;
				}
			}
		}
		return new \SoapVar($cheque, SOAP_ENC_OBJECT, PaylineService::soap_cheque, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/****************************************************/
	// 						WALLET						//
	/****************************************************/
	
	/**
	 * function wallet
	 * @params : array : array.  the array keys are listed in pl_wallet CLASS.
	 * @params : address : array.  the array keys are listed in pl_address CLASS.
	 * @params : card : array.  the array keys are listed in pl_card CLASS.
	 * @return : wallet: pl_wallet Object.
	 * @description : build a wallet object.
	 **/
	protected function wallet($array,$address,$card) {
		$wallet = new pl_wallet();
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $wallet)&&(strlen($v)))$wallet->$k = $v;
			}
		}
	
		$wallet->shippingAddress = $this->address($address);
		$wallet->card = $this->card($card);
	
		return $wallet;
	}
	
	/**
	 * function recurring
	 * @params : array : array. the array keys are listed in pl_recurring CLASS.
	 * @return : recurring object.
	 * @description : build a recurring object.
	 **/
	protected function recurring($array) {
		if($array){
			$recurring = new pl_recurring();
			if($array && is_array($array)){
				foreach($array as $k=>$v){
					if(array_key_exists($k, $recurring)&&(strlen($v)))$recurring->$k = $v;
				}
			}
			return $recurring;
		}
		else return null;
	}
	
	/**
	 * function setItem
	 * @params : $item : array. the array keys are listed in PL_ORDERDETAIL CLASS.
	 * @description : Make $item SoapVar object and insert in items array
	 **/
	public function setItem($item) {
		$orderDetail = new pl_orderDetail();
		if($item && is_array($item)){
			foreach($item as $k=>$v){
				if(array_key_exists($k, $orderDetail)&&(strlen($v)))$orderDetail->$k = $v;
			}
		}
		$this->items[] = new \SoapVar($orderDetail, SOAP_ENC_OBJECT, PaylineService::soap_orderDetail, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function setPrivate
	 * @params : $private : array.  the array keys are listed in PRIVATE CLASS.
	 * @description : Make $setPrivate SoapVar object  and insert in privates array
	 **/
	public function setPrivate($array) {
		$private = new pl_privateData();
		if($array && is_array($array)){
			foreach($array as $k=>$v){
				if(array_key_exists($k, $private)&&(strlen($v)))$private->$k = $v;
			}
		}
		$this->privates[] = new \SoapVar($private, SOAP_ENC_OBJECT, PaylineService::soap_privateData, PaylineService::PAYLINE_NAMESPACE);
	}
	
	/**
	 * function setWalletIdList
	 * @params : sting : string if wallet id separated by ';'.
	 * @return :
	 * @description : make an array of wallet id .
	 **/
	public function setWalletIdList($walletIdList) {
		if ($walletIdList) $this->walletIdList = explode(";", $walletIdList);
		if(empty($walletIdList))$this->walletIdList = array(0) ;
	}
	
	private function maskAccessKey($accessKey){
		$maskedAccessKey = substr($accessKey,0,2);
		$maskedAccessKey .= substr("********************",0,strlen($accessKey)-4);
		$maskedAccessKey .= substr($accessKey,-2);
		return $maskedAccessKey;
	}
	
	/**
	 * @method writeTrace
	 * @desc write a trace in Payline log file
	 * @param $trace : the string to add in the log file
	 */
	public function writeTrace($trace){
		if(!isset($this->paylineTrace)){
			$this->paylineTrace = new Log(date('Y-m-d',time()).'.log');
		}
		$this->paylineTrace->write($trace);
	}
	
	private function webServiceRequest($array,$WSRequest,$PaylineAPI,$Method){
		try{
			$client = new \SoapClient(dirname(__FILE__).'/'.PaylineService::WSDL, $this->header_soap);
			//var_dump($this->header_soap);die;
			$client->__setLocation($this->webServicesEndpoint.$PaylineAPI);
			$this->writeTrace("webServiceRequest($Method) - Location : ".$this->webServicesEndpoint.$PaylineAPI);
			if(isset($array['version'])&& strlen($array['version'])) $this->version = $array['version'];
			if(isset($array['media'])&& strlen($array['media'])) $this->media = $array['media'];
				
			switch($Method){
				case 'createMerchant':
					$WSresponse = $client->createMerchant($WSRequest);
					break;
				case 'createWallet':
					$WSresponse = $client->createWallet($WSRequest);
					break;
				case 'createWebWallet':
					$WSresponse = $client->createWebWallet($WSRequest);
					break;
				case 'disablePaymentRecord':
					$WSresponse = $client->disablePaymentRecord($WSRequest);
					break;
				case 'disableWallet':
					$WSresponse = $client->disableWallet($WSRequest);
					break;
				case 'doAuthorization':
					$WSresponse = $client->doAuthorization($WSRequest);
					break;
				case 'doCapture':
					$WSresponse = $client->doCapture($WSRequest);
					break;
				case 'doCredit':
					$WSresponse = $client->doCredit($WSRequest);
					break;
				case 'doDebit':
					$WSresponse = $client->doDebit($WSRequest);
					break;
				case 'doImmediateWalletPayment':
					$WSresponse = $client->doImmediateWalletPayment($WSRequest);
					break;
				case 'doReAuthorization':
					$WSresponse = $client->doReAuthorization($WSRequest);
					break;
				case 'doRecurrentWalletPayment':
					$WSresponse = $client->doRecurrentWalletPayment($WSRequest);
					break;
				case 'doRefund':
					$WSresponse = $client->doRefund($WSRequest);
					break;
				case 'doReset':
					$WSresponse = $client->doReset($WSRequest);
					break;
				case 'doScheduledWalletPayment':
					$WSresponse = $client->doScheduledWalletPayment($WSRequest);
					break;
				case 'doScoringCheque':
					$WSresponse = $client->doScoringCheque($WSRequest);
					break;
				case 'doWebPayment':
					$WSresponse = $client->doWebPayment($WSRequest);
					break;
				case 'enableWallet':
					$WSresponse = $client->enableWallet($WSRequest);
					break;
				case 'getBalance':
					$WSresponse = $client->getBalance($WSRequest);
					break;
				case 'getCards':
					$WSresponse = $client->getCards($WSRequest);
					break;
				case 'getEncryptionKey':
					$WSresponse = $client->getEncryptionKey($WSRequest);
					break;
				case 'getMerchantSettings':
					$WSresponse = $client->getMerchantSettings($WSRequest);
					break;
				case 'getPaymentRecord':
					$WSresponse = $client->getPaymentRecord($WSRequest);
					break;
				case 'getTransactionDetails':
					$WSresponse = $client->getTransactionDetails($WSRequest);
					break;
				case 'getWallet':
					$WSresponse = $client->getWallet($WSRequest);
					break;
				case 'getWebPaymentDetails':
					$WSresponse = $client->getWebPaymentDetails($WSRequest);
					break;
				case 'getWebWallet':
					$WSresponse = $client->getWebWallet($WSRequest);
					break;
				case 'manageWebWallet' :
					$WSresponse = $client->manageWebWallet($WSRequest);
					break;
				case 'transactionsSearch':
					$WSresponse = $client->transactionsSearch($WSRequest);
					break;
				case 'updateWallet':
					$WSresponse = $client->updateWallet($WSRequest);
					break;
				case 'updateWebWallet':
					$WSresponse = $client->updateWebWallet($WSRequest);
					break;
				case 'verifyAuthentication':
					$WSresponse = $client->verifyAuthentication($WSRequest);
					break;
				case 'verifyEnrollment':
					$WSresponse = $client->verifyEnrollment($WSRequest);
					break;
			}
			if($Method == 'getCards'){
				$response = util::responseToArrayForGetCards($WSresponse);
	
			}else{
				$response = util::responseToArray($WSresponse);
			}
			return $response;
		}catch ( Exception $e ) {
			$this->writeTrace("Exception : ".$e->getMessage());
			$ERROR = new pl_result();
			$ERROR->code = 'XXXXX';
			$ERROR->longMessage = $e->getMessage();
			$ERROR->shortMessage = $e->getMessage();
			return $ERROR;
		}
	}
	
	public function createWallet($array){
		if(!isset($array['walletContracts'])||!strlen($array['walletContracts'][0]))$array['walletContracts'] = '';
		$WSRequest = array (
				'version' => $this->version,
				'contractNumber' => $array['contractNumber'],
				'wallet' =>  $this->wallet($array['wallet'],$array['address'],$array['card']),
				'buyer' => $this->buyer($array['buyer'],$array['billingAddress'],$array['shippingAddress']),
				'owner' => $this->owner($array['owner'],$array['ownerAddress']),
				'privateDataList' => $this->privates,
				'authentication3DSecure' =>$this->authentication3DSecure($array['3DSecure']),
				'media' => $this->media,
				'contractNumberWalletList' => $this->secondContracts($array['walletContracts'])
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'createWallet');
	}
	
	public function createWebWallet($array){
		if(isset($array['customPaymentPageCode'])&& strlen($array['customPaymentPageCode'])) $this->customPaymentPageCode = $array['customPaymentPageCode'];
		if(isset($array['cancelURL'])&& strlen($array['cancelURL'])) $this->cancelURL = $array['cancelURL'];
		if(isset($array['notificationURL']) && strlen($array['notificationURL'])) $this->notificationURL = $array['notificationURL'];
		if(isset($array['returnURL'])&& strlen($array['returnURL'])) $this->returnURL = $array['returnURL'];
		if(isset($array['customPaymentTemplateURL'])&& strlen($array['customPaymentTemplateURL'])) $this->customPaymentTemplateURL = $array['customPaymentTemplateURL'];
		if(isset($array['customPaymentPageCode'])&& strlen($array['customPaymentPageCode'])) $this->customPaymentPageCode = $array['customPaymentPageCode'];
		if(isset($array['languageCode'])&& strlen($array['languageCode'])) $this->languageCode = $array['languageCode'];
		if(isset($array['securityMode'])&& strlen($array['securityMode'])) $this->securityMode = $array['securityMode'];
		if(!isset($array['contracts'])||!strlen($array['contracts'][0]))$array['contracts'] = '';
		if(!isset($array['walletContracts'])||!strlen($array['walletContracts'][0]))$array['walletContracts'] = '';
		$WSRequest = array (
				'version' => $this->version,
				'contractNumber' => $array['contractNumber'],
				'selectedContractList' => $this->contracts($array['contracts']),
				'updatePersonalDetails' => $array['updatePersonalDetails'],
				'buyer' => $this->buyer($array['buyer'],$array['billingAddress'],$array['shippingAddress']),
				'languageCode' => $this->languageCode,
				'customPaymentPageCode' => $this->customPaymentPageCode,
				'securityMode' => $this->securityMode,
				'returnURL' => $this->returnURL,
				'cancelURL' => $this->cancelURL,
				'notificationURL' => $this->notificationURL,
				'privateDataList' => $this->privates,
				'customPaymentTemplateURL' => $this->customPaymentTemplateURL,
				'contractNumberWalletList' => $this->secondContracts($array['walletContracts'])
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::WEB_API,'createWebWallet');
	}
	
	public function disablePaymentRecord($array){
		$WSRequest = array (
				'contractNumber' => $array['contractNumber'],
				'paymentRecordId' =>  $array['paymentRecordId']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'disablePaymentRecord');
	}
	
	public function disableWallet($array){
		$WSRequest = array (
				'contractNumber' => $array['contractNumber'],
				'walletIdList' =>  $this->walletIdList,
				'cardInd' => $array['cardInd']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'disableWallet');
	}
	
	public function doAuthorization($array){
		if(!isset($array['buyer']))$array['buyer'] = null;
		if(!isset($array['billingAddress']))$array['billingAddress'] = null;
		if(!isset($array['shippingAddress']))$array['shippingAddress'] = null;
		$WSRequest = array (
				'version' => $this->version,
				'payment' => $this->payment($array['payment']),
				'card' =>  $this->card($array['card']),
				'order' => $this->order($array['order']),
				'buyer' => $this->buyer($array['buyer'],$array['billingAddress'],$array['shippingAddress']),
				'privateDataList' =>  $this->privates,
				'authentication3DSecure' =>$this->authentication3DSecure($array['3DSecure']),
				'bankAccountData' => $this->bankAccountData($array['BankAccountData']),
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doAuthorization');
	}
	
	public function doCapture($array){
		$WSRequest = array (
				'version' => $this->version,
				'transactionID' =>$array['transactionID'],
				'payment' =>  $this->payment($array['payment']),
				'privateDataList' =>  $this->privates,
				'sequenceNumber'=>$array['sequenceNumber'],
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doCapture');
	}
	
	public function doCredit($array){
		if(!isset($array['buyer']))$array['buyer'] = null;
		if(!isset($array['billingAddress']))$array['billingAddress'] = null;
		if(!isset($array['shippingAddress']))$array['shippingAddress'] = null;
		$WSRequest = array (
				'version' => $this->version,
				'payment' => $this->payment($array['payment']),
				'card' =>  $this->card($array['card']),
				'buyer' => $this->buyer($array['buyer'],$array['billingAddress'],$array['shippingAddress']),
				'privateDataList' => $this->privates,
				'order' => $this->order($array['order']),
				'comment' =>$array['comment'],
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doCredit');
	}
	
	public function doDebit($array){
		if(!isset($array['buyer']))$array['buyer'] = null;
		if(!isset($array['billingAddress']))$array['billingAddress'] = null;
		if(!isset($array['shippingAddress']))$array['shippingAddress'] = null;
		$WSRequest = array (
				'version' => $this->version,
				'payment' => $this->payment($array['payment']),
				'card' =>  $this->card($array['card']),
				'order' => $this->order($array['order']),
				'privateDataList' =>  $this->privates,
				'buyer' => $this->buyer($array['buyer'],$array['billingAddress'],$array['shippingAddress']),
				'authentication3DSecure' =>$this->authentication3DSecure($array['3DSecure']),
				'authorization' =>$this->authorization($array['authorization']),
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doDebit');
	}
	
	public function doImmediateWalletPayment($array){
		$WSRequest = array (
				'version' => $this->version,
				'payment' => $this->payment($array['payment']),
				'order' =>  $this->order($array['order']),
				'walletId' =>  $array['walletId'],
				'cardInd' => $array['cardInd'],
				'privateDataList' => $this->privates,
				'media' => $this->media,
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doImmediateWalletPayment');
	}
	
	public function doReAuthorization($array){
		$WSRequest = array (
				'version' => $this->version,
				'transactionID' => $array['transactionID'],
				'payment' => $this->payment($array['payment']),
				'order' => $this->order($array['order']),
				'privateDataList' =>  $this->privates,
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doReAuthorization');
	}
	
	public function doRecurrentWalletPayment($array){
		$WSRequest = array (
				'version' => $this->version,
				'payment' => $this->payment($array['payment']),
				'orderRef' => $array['orderRef'],
				'orderDate' => $array['orderDate'],
				'scheduledDate' => $array['scheduled'],
				'walletId' =>  $array['walletId'],
				'cardInd' => $array['cardInd'],
				'recurring' =>  $this->recurring($array['recurring']),
				'privateDataList' =>  $this->privates,
				'order' => $this->order($array['order']),
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doRecurrentWalletPayment');
	}
	
	public function doRefund($array){
		$WSRequest = array (
				'version' => $this->version,
				'transactionID' =>$array['transactionID'],
				'payment' =>$this->payment($array['payment']),
				'comment' =>$array['comment'],
				'privateDataList' =>  $this->privates,
				'sequenceNumber'=>$array['sequenceNumber'],
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doRefund');
	}
	
	public function doReset($array){
		$WSRequest = array (
				'version' => $this->version,
				'transactionID' => $array['transactionID'],
				'comment' => $array['comment'],
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doReset');
	}
	
	public function doScheduledWalletPayment($array){
		$WSRequest = array (
				'version' => $this->version,
				'payment' => $this->payment($array['payment']),
				'orderRef' => $array['orderRef'],
				'orderDate' => $array['orderDate'],
				'scheduledDate' => $array['scheduled'],
				'walletId' =>  $array['walletId'],
				'cardInd' => $array['cardInd'],
				'order' =>  $this->order($array['order']),
				'privateDataList' => $this->privates,
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doScheduledWalletPayment');
	}
	
	public function doScoringCheque($array){
		$WSRequest = array (
				'version' => $this->version,
				'payment' => $this->payment($array['payment']),
				'cheque' => $this->cheque($array['cheque']),
				'order' => $this->order($array['order']),
				'privateDataList' => $this->privates,
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'doScoringCheque');
	}
	
	public function doWebPayment($array){
		if(isset($array['cancelURL'])&& strlen($array['cancelURL'])) $this->cancelURL = $array['cancelURL'];
		if(isset($array['notificationURL']) && strlen($array['notificationURL'])) $this->notificationURL = $array['notificationURL'];
		if(isset($array['returnURL'])&& strlen($array['returnURL'])) $this->returnURL = $array['returnURL'];
		if(isset($array['customPaymentTemplateURL'])&& strlen($array['customPaymentTemplateURL'])) $this->customPaymentTemplateURL = $array['customPaymentTemplateURL'];
		if(isset($array['customPaymentPageCode'])&& strlen($array['customPaymentPageCode'])) $this->customPaymentPageCode = $array['customPaymentPageCode'];
		if(isset($array['languageCode'])&& strlen($array['languageCode'])) $this->languageCode = $array['languageCode'];
		if(isset($array['securityMode'])&& strlen($array['securityMode'])) $this->securityMode = $array['securityMode'];
		if(!isset($array['payment']))$array['payment'] = null;
		if(!isset($array['contracts'])||!strlen($array['contracts'][0]))$array['contracts'] = '';
		if(!isset($array['secondContracts'])||!strlen($array['secondContracts'][0]))$array['secondContracts'] = '';
		if(!isset($array['walletContracts'])||!strlen($array['walletContracts'][0]))$array['walletContracts'] = '';
		if(!isset($array['buyer']))$array['buyer'] = null;
		if(!isset($array['billingAddress']))$array['billingAddress'] = null;
		if(!isset($array['shippingAddress']))$array['shippingAddress'] = null;
		if(!isset($array['recurring']))$array['recurring'] = null;
		$WSRequest = array (
				'version' => $this->version,
				'payment' => $this->payment($array['payment']),
				'returnURL' => $this->returnURL,
				'cancelURL' => $this->cancelURL,
				'order' => $this->order($array['order']),
				'notificationURL' => $this->notificationURL,
				'customPaymentTemplateURL' => $this->customPaymentTemplateURL,
				'selectedContractList' => $this->contracts($array['contracts']),
				'secondSelectedContractList' => $this->secondContracts($array['secondContracts']),
				'privateDataList' => $this->privates,
				'languageCode' => $this->languageCode,
				'customPaymentPageCode' => $this->customPaymentPageCode,
				'buyer' => $this->buyer($array['buyer'],$array['billingAddress'],$array['shippingAddress']),
				'securityMode' => $this->securityMode,
				'contractNumberWalletList' => $this->secondContracts($array['walletContracts'])
		);
			
		if(isset($array['payment']['mode'])){
			if(($array['payment']['mode'] == "REC") || ($array['payment']['mode'] == "NX")) {
				$WSRequest['recurring'] = $this->recurring($array['recurring']);
			}
		}
		return $this->webServiceRequest($array,$WSRequest,PaylineService::WEB_API,'doWebPayment');
	}
	
	public function enableWallet($array){
		$WSRequest = array (
				'contractNumber' => $array['contractNumber'],
				'walletId' =>  $array['walletId'],
				'cardInd' => $array['cardInd']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'enableWallet');
	}
	
	public function getBalance($array){
		$WSRequest = array(
				'contractNumber' => $array['contractNumber'],
				'cardID' => $array['cardID']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'getBalance');
	}
	
	public function getCards($array){
		$WSRequest = array (
				'contractNumber' => $array['contractNumber'],
				'walletId' =>  $array['walletId'],
				'cardInd' => $array['cardInd']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'getCards');
	}
	
	public function getEncryptionKey($array){
		$WSRequest = array();
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'getEncryptionKey');
	}
	
	public function getMerchantSettings($array){
		$WSRequest = array(
				'version' => $this->version
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'getMerchantSettings');
	}
	
	public function getPaymentRecord($array){
		$WSRequest = array (
				'version' => $this->version,
				'contractNumber' => $array['contractNumber'],
				'paymentRecordId' =>  $array['paymentRecordId']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'getPaymentRecord');
	}
	public function getTransactionDetails($array){
		$WSRequest = array (
				'version' => $this->version,
				'transactionId' => $array['transactionId'],
				'orderRef' =>  $array['orderRef'],
				'startDate' => $array['startDate'],
				'endDate' => $array['endDate'],
				'transactionHistory' => $array['transactionHistory'],
				'archiveSearch' => $array['archiveSearch']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::EXTENDED_API,'getTransactionDetails');
	}
	public function getWallet($array){
		$WSRequest = array (
				'version' => $this->version,
				'contractNumber' => $array['contractNumber'],
				'walletId' =>  $array['walletId'],
				'cardInd' => $array['cardInd'],
				'media' => $this->media
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'getWallet');
	}
	
	public function getWebPaymentDetails($array){
		return $this->webServiceRequest($array,$array,PaylineService::WEB_API,'getWebPaymentDetails');
	}
	
	public function getWebWallet($array){
		return $this->webServiceRequest($array,$array,PaylineService::WEB_API,'getWebWallet');
	}
	
	public function manageWebWallet($array){
		if(isset($array['cancelURL'])&& strlen($array['cancelURL'])) $this->cancelURL = $array['cancelURL'];
		if(isset($array['notificationURL']) && strlen($array['notificationURL'])) $this->notificationURL = $array['notificationURL'];
		if(isset($array['returnURL'])&& strlen($array['returnURL'])) $this->returnURL = $array['returnURL'];
		if(!isset($array['buyer']))$array['buyer'] = null;
		if(!isset($array['billingAddress']))$array['billingAddress'] = null;
		if(!isset($array['shippingAddress']))$array['shippingAddress'] = null;
		if(!isset($array['owner']))$array['owner'] = null;
		if(!isset($array['ownerAddress']))$array['ownerAddress'] = null;
		if(!isset($array['contracts'])||!strlen($array['contracts'][0]))$array['contracts'] = '';
		if(!isset($array['walletContracts'])||!strlen($array['walletContracts'][0]))$array['walletContracts'] = '';
		if(isset($array['customPaymentPageCode'])&& strlen($array['customPaymentPageCode'])) $this->customPaymentPageCode = $array['customPaymentPageCode'];
		if(isset($array['customPaymentTemplateURL'])&& strlen($array['customPaymentTemplateURL'])) $this->customPaymentTemplateURL = $array['customPaymentTemplateURL'];
		$WSRequest = array (
				'version' => $this->version,
				'contractNumber' => $array['contractNumber'],
				'selectedContractList' => $this->contracts($array['contracts']),
				'updatePersonalDetails' => $array['updatePersonalDetails'],
				'buyer' => $this->buyer($array['buyer'],$array['billingAddress'],$array['shippingAddress']),
				'owner' => $this->owner($array['owner'],$array['ownerAddress']),
				'languageCode' => $array['languageCode'],
				'customPaymentPageCode' => $array['customPaymentPageCode'],
				'securityMode' => $array['securityMode'],
				'returnURL' => $this->returnURL,
				'cancelURL' => $this->cancelURL,
				'notificationURL' => $this->notificationURL,
				'privateDataList' => $this->privates,
				'customPaymentTemplateURL' => $array['customPaymentTemplateURL'],
				'contractNumberWalletList' => $this->secondContracts($array['walletContracts'])
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::WEB_API,'manageWebWallet');
	}
	
	public function transactionsSearch($array){
		$WSRequest = array (
				'version' => $this->version,
				'transactionId' => $array['transactionId'],
				'orderRef' => $array['orderRef'],
				'startDate' =>  $array['startDate'],
				'endDate' =>  $array['endDate'],
				'contractNumber' => $array['contractNumber'],
				'authorizationNumber' =>  $array['authorizationNumber'],
				'returnCode'  => $array['returnCode'],
				'paymentMean' =>  $array['paymentMean'],
				'transactionType' =>  $array['transactionType'],
				'name' =>  $array['name'],
				'firstName' =>  $array['firstName'],
				'email' =>  $array['email'],
				'cardNumber' =>  $array['cardNumber'],
				'currency' =>  $array['currency'],
				'minAmount' =>  $array['minAmount'],
				'maxAmount' =>  $array['maxAmount'],
				'walletId' =>  $array['walletId'],
				'sequenceNumber' => $array['sequenceNumber'],
				'token' => $array['token']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::EXTENDED_API,'transactionsSearch');
	}
	
	public function updateWallet($array){
		$WSRequest = array (
				'version' => $this->version,
				'contractNumber' => $array['contractNumber'],
				'cardInd' => $array['cardInd'],
				'wallet' => $this->wallet($array['wallet'],$array['address'],$array['card']),
				'buyer' => $this->buyer($array['buyer'], $array['billingAddress'],$array['shippingAddress']),
				'owner' => $this->owner($array['owner'],$array['ownerAddress']),
				'privateDataList' => $this->privates,
				'authentication3DSecure' =>$this->authentication3DSecure($array['3DSecure']),
				'media' => $this->media,
				'contractNumberWalletList' => $this->secondContracts($array['walletContracts'])
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'updateWallet');
	}
	
	public function updateWebWallet($array){
		if(isset($array['cancelURL'])&& strlen($array['cancelURL'])) $this->cancelURL = $array['cancelURL'];
		if(isset($array['notificationURL']) && strlen($array['notificationURL'])) $this->notificationURL = $array['notificationURL'];
		if(isset($array['returnURL'])&& strlen($array['returnURL'])) $this->returnURL = $array['returnURL'];
		if(isset($array['customPaymentTemplateURL'])&& strlen($array['customPaymentTemplateURL'])) $this->customPaymentTemplateURL = $array['customPaymentTemplateURL'];
		if(isset($array['customPaymentPageCode'])&& strlen($array['customPaymentPageCode'])) $this->customPaymentPageCode = $array['customPaymentPageCode'];
		if(isset($array['languageCode'])&& strlen($array['languageCode'])) $this->languageCode = $array['languageCode'];
		if(isset($array['securityMode'])&& strlen($array['securityMode'])) $this->securityMode = $array['securityMode'];
		if(!isset($array['walletContracts'])||!strlen($array['walletContracts'][0]))$array['walletContracts'] = '';
		$WSRequest = array (
				'version' => $this->version,
				'contractNumber' => $array['contractNumber'],
				'cardInd' => $array['cardInd'],
				'walletId' => $array['walletId'],
				'updatePersonalDetails' => $array['updatePersonalDetails'],
				'updateOwnerDetails' => $array['updateOwnerDetails'],
				'updatePaymentDetails' => $array['updatePaymentDetails'],
				'buyer' => $this->buyer($array['buyer'],$array['billingAddress'],$array['shippingAddress']),
				'languageCode' => $this->languageCode,
				'customPaymentPageCode' => $this->customPaymentPageCode,
				'securityMode' => $this->securityMode,
				'returnURL' => $this->returnURL,
				'cancelURL' => $this->cancelURL,
				'notificationURL' => $this->notificationURL,
				'privateDataList' => $this->privates,
				'customPaymentTemplateURL' => $this->customPaymentTemplateURL,
				'contractNumberWalletList' => $this->secondContracts($array['walletContracts'])
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::WEB_API,'updateWebWallet');
	}
	
	public function verifyAuthentication($array){
		$WSRequest = array (
				'contractNumber' => $array['contractNumber'],
				'pares' =>  $array['pares'],
				'md' =>  $array['md'],
				'card' =>  $this->card($array['card'])
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'verifyAuthentication');
	}
	
	public function verifyEnrollment($array){
		$WSRequest = array (
				'payment' => $this->payment($array['payment']),
				'card' =>  $this->card($array['card']),
				'orderRef' => $array['orderRef'],
				'userAgent' => $array['userAgent']
		);
		return $this->webServiceRequest($array,$WSRequest,PaylineService::DIRECT_API,'verifyEnrollment');
	}
}

class util{

	/**
	 * make an array from a payline server response object.
	 * @params : $response : Objet response from payline
	 * @return : Object convert in an array
	 **/
	static function responseToArray($response){

		$array = array();
		foreach($response as $k=>$v){
			if (is_object($v))  {
				$array[$k] = util::responseToArray($v);
			}
			else { $array[$k] = $v;
			}
		}
		return $array;

		return $response;
	}

	static function responseToArrayForGetCards($response){

		$array = array();
		foreach($response as $k=>$v){

			if (is_object($v) && ($k != 'cards' ) )  {
				$array[$k] = util::responseToArrayForGetCards($v);
			}
			else {
				if($k == 'cards' && count($v) == 1 ){
					$array[$k][0] = $v;
				}else{
					$array[$k] = $v;
				}
			}
		}
		return $array;

		return $response;
	}
}

//
// PL_PAYMENT OBJECT DEFINITION
//
class pl_payment{

	// ATTRIBUTES LISTING
	public $amount;
	public $currency;
	public $action;
	public $mode;
	public $contractNumber;
	public $differedActionDate;
}

//
// PL_ORDER OBJECT DEFINITION
//
class pl_order{

	// ATTRIBUTES LISTING
	public $ref;
	public $origin;
	public $country;
	public $taxes;
	public $amount;
	public $currency;
	public $date;
	public $quantity;
	public $comment;
	public $details;

	function __construct() {
		$this->date = date('d/m/Y H:i', time());
		$this->details = array();
	}
}

//
// PL_PRIVATEDATA OBJECT DEFINITION
//
class pl_privateData{

	// ATTRIBUTES LISTING
	public $key ;
	public $value;
}

//
// PL_AUTHORIZATION OBJECT DEFINITION
//
class  pl_authorization{

	// ATTRIBUTES LISTING
	public $number;
	public $date;
}

//
// PL_ADDRESS OBJECT DEFINITION
//
class  pl_address{

	// ATTRIBUTES LISTING
	public $name;
	public $street1;
	public $street2;
	public $cityName;
	public $zipCode;
	public $country;
	public $phone;
}

//
// PL_BUYER OBJECT DEFINITION
//
class pl_buyer{

	// ATTRIBUTES LISTING
	public $lastName;
	public $firstName;
	public $email;
	public $customerId;
	public $walletId;
	public $walletDisplayed;
	public $walletSecured;
	public $walletCardInd;
	public $shippingAdress;
	public $billingAddress;
	public $accountCreateDate;
	public $accountAverageAmount;
	public $accountOrderCount;
	public $ip;
	public $mobilePhone;

	function __construct() {
		$this->accountCreateDate = date('d/m/y', time());
	}
}

//
// PL_OWNER OBJECT DEFINITION
//
class pl_owner{

	// ATTRIBUTES LISTING
	public $lastName;
	public $firstName;
	public $billingAddress;
	public $issueCardDate;
}

//
// PL_ORDERDETAIL OBJECT DEFINITION
//
class pl_orderDetail{

	// ATTRIBUTES LISTING
	public $ref;
	public $price;
	public $quantity;
	public $comment;
}

//
// PL_CARD OBJECT DEFINITION
//
class pl_card{

	// ATTRIBUTES LISTING
	public $number;
	public $type;
	public $expirationDate;
	public $cvx;
	public $ownerBirthdayDate;
	public $password;
	public $token;

	function __construct($type) {
		$this->accountCreateDate = date('d/m/y', time());
	}
}

//
// PL_TRANSACTION OBJECT DEFINITION
//
class pl_transaction{

	// ATTRIBUTES LISTING
	public $id;
	public $isPossibleFraud;
	public $isDuplicated;
	public $date;
}


//
// PL_RESULT OBJECT DEFINITION
//
class pl_result{

	// ATTRIBUTES LISTING
	public $code;
	public $shortMessage;
	public $longMessage;
}

//
// PL_CAPTURE OBJECT DEFINITION
//
class pl_capture{

	// ATTRIBUTES LISTING
	public $transactionID;
	public $payment;
	public $sequenceNumber;

	function __construct() {
		$this->payment = new pl_payment();
	}
}

//
// PL_REFUND OBJECT DEFINITION
//
class pl_refund extends pl_capture {
	function __construct() {
		parent::__construct();
	}
}

//
// PL_WALLET OBJECT DEFINITION
//
class pl_wallet{

	// ATTRIBUTES LISTING
	public $walletId;
	public $lastName;
	public $firstName;
	public $email;
	public $shippingAddress;
	public $card;
	public $comment;

	function __construct() {
	}
}

//
// PL_RECURRING OBJECT DEFINITION
//
class pl_recurring{

	// ATTRIBUTES LISTING
	public $firstAmount;
	public $amount;
	public $billingCycle;
	public $billingLeft;
	public $billingDay;
	public $startDate;

	function __construct() {
	}
}

//
// PL_AUTHENTIFICATION 3D SECURE
//
class pl_authentication3DSecure{

	// ATTRIBUTES LISTING
	public $md ;
	public $pares ;
	public $xid ;
	public $eci ;
	public $cavv ;
	public $cavvAlgorithm ;
	public $vadsResult ;

	function __construct() {
	}
}

//
// PL_BANKACCOUNTDATA
//
class pl_bankAccountData{


	// ATTRIBUTES LISTING
	public $countryCode ;
	public $bankCode ;
	public $accountNumber ;
	public $key ;


	function __construct() {
	}
}

//
// PL_CHEQUE
//
class pl_cheque{

	// ATTRIBUTES LISTING
	public $number ;

	function __construct() {
	}
}

final class Log {
	private $filename;
	private $path;

	public function __construct($filename) {
		$this->filename = $filename;
		$tmp = explode(DIRECTORY_SEPARATOR ,dirname(__FILE__));

		// up one level from the current directory
		for($i=0,$s = sizeof($tmp)-1; $i<$s; $i++){
			$this->path .= $tmp[$i].DIRECTORY_SEPARATOR;
		}
		$this->path .= 'logs'.DIRECTORY_SEPARATOR;
	}

	public function write($message) {
		/*$file = $this->path.$this->filename;
		$handle = fopen($file, 'a+');
		fwrite($handle, date('Y-m-d G:i:s') . ' - ' . $message . "\n");
		fclose($handle);*/
	}
}
