<?php

namespace Stloc\Bundle\PaylineBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    
    public function indexAction()
    {
    	$paylineService = $this->get('payline');
    	 
    	 
    	$actions = array(
    			'doWebPayment',
    			'getWebPaymentDetail'
    	);
    	$selected = isset( $_GET['e'] ) ? $_GET['e'] : $actions[0];
    	if ( !in_array($selected, $actions) ) $selected = $actions[0];
    	$links = '<h3>';
    	foreach( $actions as $v )
    		$links .= ( $v==$selected ) ? "$v - " : "<a href='?e=$v'>$v</a> - ";
    	$links = substr( $links, 0, -2 ).'</h3>';
    	 
    	if ($this->getRequest()->getMethod() == 'POST') {
    		// if you want to override default options
    		//$paylineService->returnURL = '';
    		//$paylineService->cancelURL = '';
    		//$paylineService->notificationURL = '';
    
    		// PAYMENT
    		$array['payment']['amount'] = $_POST['amount'];
    		$array['payment']['currency'] = $_POST['currency'];
    		$array['payment']['action'] = $paylineService->getOption('PAYMENT_ACTION');
    		$array['payment']['mode'] = $paylineService->getOption('PAYMENT_MODE');
    
    		// ORDER
    		$array['order']['ref'] = $_POST['ref'];
    		$array['order']['amount'] = $_POST['amount'];
    		$array['order']['currency'] = $_POST['currency'];
    
    		// CONTRACT NUMBERS
    		$array['payment']['contractNumber'] = $paylineService->getOption('CONTRACT_NUMBER');
    		$contracts = explode(";",$paylineService->getOption('CONTRACT_NUMBER_LIST'));
    		$array['contracts'] = $contracts;
    		$secondContracts = explode(";",$paylineService->getOption('SECOND_CONTRACT_NUMBER_LIST'));
    		$array['secondContracts'] = $secondContracts;
    
    		// EXECUTE
    		$result = $paylineService->doWebPayment($array);
    
    		// RESPONSE
    		if(isset($result) && $result['result']['code'] == '00000'){
    			header("location:".$result['redirectURL']);
    			exit();
    		}
    		elseif(isset($result)) {
    			echo 'ERROR : '.$result['result']['code']. ' '.$result['result']['longMessage'].' <BR/>';
    		}
    
    
    	}
    	 
    	return $this->render('StlocPaylineBundle:Default:index.html.twig', array(
    			'actions' => $actions,
    			'links' => $links,
    			'paylineServiceOptions' => $paylineService->getOptions(),
    			'selected' => $selected,
    			'time' => time(),
    			'paylineService' => $paylineService
    	));
    }
}
