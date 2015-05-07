<?php
defined('_JEXEC') or die('Restricted access');


require_once (JPATH_ADMINISTRATOR.'/components/com_k2store/library/plugins/payment.php');

class Model extends K2StorePaymentPlugin
{
	/**
	 * @var $_element  string  Should always correspond with the plugin's filename,
	 *                         forcing it to be unique
	 */
    public $_element    = 'payment_checkoutapipayment';
    var $secret_key  = '';
    var $publishable_key ='';
    var $trans_type    = '';
    var $trans_type_values = array(
    	'Authorize_Capture' => 'AUTHORIZE AND CAPTURE',
    	'Authorize'=>'AUTHORIZE'
    		);
    var $auto_capture_time ='';
    var $timeout = '';
    var $endpoint = '';
    var $endpoint_values = array(
    	'Dev' => 'Development',
    	'Preprod' => 'Preprod',
    	'Live' => 'Live'
    	);
    var $ispci ='';
    var $ispci_values = array(
    	'Yes' => 'Yes',
    	'No' => 'No'
    	);
    var $_isLog      = true;
    var $_k2version = null;
    private $_instance;
    private $_prePaymentFile;
    private $_currentCharge;

    function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage( '', JPATH_ADMINISTRATOR );

        $this->secret_key = $this->public_key = trim($this->params->get('secret_key'));
        $this->publishable_key = $this->public_key = trim($this->params->get('publishable_key'));
		$this->trans_type = $this->public_key = trim($this->params->get('transaction_type'));
		$this->auto_capture_time = $this ->auto_capture_time = trim($this->params->get('auto_capture_time'));
		$this->timeout = $this ->timeout= trim($this->params->get('timeout'));
		$this->endpoint = $this->endpoint = trim($this->params->get('endpoint'));
		$this->ispci = $this->ispci = trim($this->params->get('ispci'));
        $this->_j2version = $this->getVersion();

	}

	function getInstance()
	{
	    if(!$this->_instance) {

	        switch($this->ispci) {
	            case 'Yes':
	                $this->_instance = new model_methods_creditcardpci();
	            break;
	            default :
	                $this->_instance =  new model_methods_creditcard();
	                break;
	        }
	    }

	     return $this->_instance;
	}

    function getPrepayment(){
        if(!$this->_prePaymentFile) {

            switch($this->ispci) {
                case 'Yes':
                    $this->_prePaymentFile = 'prepayment';
                break;
                default :
                    $this->_prePaymentFile = 'prepayment_nonpci';
                    break;
            }
        }

        return $this->_prePaymentFile;

    }



	function _prePayment ($data)
	{
		//initialise
    	$app = JFactory::getApplication();

        // prepare the payment form
        $vars = new JObject();
        $vars->url = JRoute::_( "index.php?option=com_k2store&view=mycart" );
        $vars->order_id = $data['order_id'];
        $vars->orderpayment_id = $data['orderpayment_id'];

        JTable::addIncludePath(JPATH_ADMINISTRATOR.'/components/com_k2store/tables');
        $order = JTable::getInstance('Orders', 'Table');
        $order->load($data['orderpayment_id']);
        $currency_values= $this->getCurrency($order);
        $vars->currency_code =$currency_values['currency_code'];
        $vars->orderpayment_amount = $this->getAmount($order->orderpayment_amount, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert']);

        $vars->orderpayment_type = $this->_element;

        if($this->ispci=="Yes"){

            $vars->cardname = $app->input->getString("cardname");
            $vars->cardtype = $app->input->getString("cardtype");
            $vars->cardnum = $app->input->getString("cardnum");

            $vars->cardmonth=$app->input->getString("month");
            $vars->cardyear=$app->input->getString("year");
            $card_exp = $vars->cardmonth.''.$vars->cardyear;
            $vars->cardexp = $card_exp;

            $vars->cardcvv = $app->input->getString("cardcvv");
            $vars->cardnum_last4 = substr( $app->input->getString("cardnum"), -4 );

        }

        else{

            require_once (JPATH_SITE.'/components/com_k2store/helpers/utilities.php');
            //get the order info from order info table
            $orderinfo = JTable::getInstance('Orderinfo', 'Table');
            $orderinfo ->load( array('order_id'=>$order->order_id));

            $currency_values= $this->getCurrency($order);
            $amount = $this->getAmount($order->orderpayment_amount, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert']);

            $amountCents = $amount *100;

            $vars->publishable_key = $this->publishable_key;
            $vars->customerEmail = $orderinfo->user_email;
            $vars->customerName = $orderinfo->billing_first_name .' '.$orderinfo->billing_last_name;
            $vars->value = $amountCents;
            $vars->currency = $order->currency_code;

            $vars->billing_addressLine1 = $orderinfo->billing_address_1;
            $vars->billing_addressLine2 = $orderinfo->billing_address_2;
            $vars->billing_postcode = $orderinfo->billing_zip;
            $vars->billing_country = $orderinfo->billing_country_name;
            $vars->billing_city = $orderinfo->billing_city;
            $vars->billing_state = $orderinfo->billing_zone_name;
            $vars->billing_phone = (!empty($orderinfo->billing_phone_1))?$orderinfo->billing_phone_1:$orderinfo->billing_phone_2;

        }



 		$vars->display_name = $this->params->get('display_name', 'PLG_K2STORE_PAYMENTS_CHECKOUTAPIPAYMENT');
        $vars->onbeforepayment_text = $this->params->get('onbeforepayment', '');
        $vars->button_text = $this->params->get('button_text', 'K2STORE_PLACE_ORDER');

        
        $html = $this->_getLayout($this->getPrepayment(), $vars);
        
        return $html;

	}

	function _postPayment ($data)
	{
		// Process the payment
        $vars = new JObject();

        $app = JFactory::getApplication();
        $paction = $app->input->getString('paction');

        switch ($paction)
        {
        	case 'display':
        		$html = JText::_($this->params->get('onafterpayment', ''));
        		$html .= $this->_displayArticle();
				break;
            case 'process':
                $result = $this->_process();
              	echo json_encode($result);
                $app->close();
				break;
            default:
                $vars->message = JText::_($this->params->get('onerrorpayment', ''));
                $html = $this->_getLayout('message', $vars);
				break;
        }

        return $html;
	}

	function _renderForm ($data)
	{
        $vars = new JObject();
        $vars->prepop = array();
        if($this->ispci=="Yes"){
            $vars->cctype_input = $this->_cardTypesField();
            $vars->onselection_text = $this->params->get('onselection', '');
            $html = $this->_getLayout('form', $vars);
        }
        else
        {
            $vars->onselection_text = $this->params->get('onselection', '');
            $html = $this->_getLayout('form_js', $vars);
        }



        return $html;
	}

	function _verifyForm($submitted_values)
	{
		return $this->getInstance()->_verifyForm($submitted_values);		
	}

	function _cardTypesField( $field='cardtype', $default='', $options='' )
	{
    	$types = array();
    	$card_types = $this->params->get('card_types', array('Visa', 'Mastercard'));
    	foreach($card_types as $type) {
    		$types[] = JHTML::_('select.option', $type, JText::_( "K2STORE_CHECKOUTAPIPAYMENT_".strtoupper($type) ) );
    	}

        $return = JHTML::_('select.genericlist', $types, $field, $options, 'value','text', $default);
	    return $return;
	}

	function _getFormattedCardExprDate($format, $value)
	{
		return $this->getInstance()->_getFormattedCardExprDate($format, $value);
	}

	function _process()
	{
        /*
         * perform initial checks
         */
        if ( ! JRequest::checkToken() ) {
            return $this->_renderHtml( JText::_( 'Invalid Token' ) );
        }

        $app = JFactory::getApplication();

        $data = $app->input->getArray($_POST);
        $json = array();
        $errors = array();
        // get order information
        JTable::addIncludePath( JPATH_ADMINISTRATOR.'/components/com_k2store/tables' );
        $order = JTable::getInstance('Orders', 'Table');
        $order->load( $data['orderpayment_id'] );
        if ( empty($order->order_id) ) {
             $json['error'] = JText::_( 'K2STORE_CHECKOUTAPIPAYMENT_INVALID_ORDER' );
        }

        if ( empty($this->secret_key) || empty($this->publishable_key)) {
            $json['error'] = JText::_( 'K2STORE_CHECKOUTAPIPAYMENT_MESSAGE_MISSING_CONFIG' );
        }

        if( !$json ){
            require_once (JPATH_SITE.'/components/com_k2store/helpers/utilities.php');
            //get the order info from order info table
            $orderinfo = JTable::getInstance('Orderinfo', 'Table');
            $orderinfo ->load( array('order_id'=>$order->order_id));


            $currency_values= $this->getCurrency($order);
            $amount = $this->getAmount($order->orderpayment_amount, $currency_values['currency_code'], $currency_values['currency_value'], $currency_values['convert']);

            $amountCents = $amount *100;

            $config = array();
            $config['authorization'] = $this->secret_key;
            $config['mode'] = $this->endpoint;

            $config['postedParam'] = array (
                'email'=>$orderinfo ->user_email ,
                'value'=>$amountCents,
                'currency'=> $order->currency_code ,
                'shippingDetails' => array (
                    'addressLine1' =>  $orderinfo->shipping_address_1,
                    'addressLine2' => $orderinfo->shipping_address_2,
                    'Postcode'    =>  $orderinfo->shipping_zip,
                    'Country'     =>  $orderinfo->shipping_country_name,
                    'City'        =>  $orderinfo->shipping_city,
                    'State'       =>  $orderinfo->shipping_zone_name,
                    'Phone'       =>  (!empty($orderinfo->shipping_phone_1))?$orderinfo->shipping_phone_1:$orderinfo->shipping_phone_2,
                    'recipientname'      =>  $orderinfo->shipping_first_name.' '.$orderinfo->shipping_first_name
                 )

            );

            if ($this->trans_type == 'Authorize_Capture') {
                $config = array_merge( $this->captureConfig(),$config);
            } else {
                $config = array_merge( $this->authorizeConfig(),$config);
            }

            if($this->ispci =="Yes")
            {
                $config['postedParam']['card']['name'] = $data['cardname'];
                $config['postedParam']['card']['number'] = $data['cardnum'];
                $config['postedParam']['card']['expiryMonth'] = $data['cardmonth'];
                $config['postedParam']['card']['expiryYear'] = $data['cardyear'];
                $config['postedParam']['card']['cvv'] = $data['cardcvv'];
                $config['postedParam']['card']['billingDetails']['addressLine1'] = $orderinfo->billing_address_1;
                $config['postedParam']['card']['billingDetails']['addressLine2'] = $orderinfo->billing_address_2;
                $config['postedParam']['card']['billingDetails']['postcode'] = $orderinfo->billing_zip;
                $config['postedParam']['card']['billingDetails']['country'] = $orderinfo->billing_country_name;
                $config['postedParam']['card']['billingDetails']['city'] = $orderinfo->billing_city;
                $config['postedParam']['card']['billingDetails']['city'] = $orderinfo->billing_zone_name;
                $config['postedParam']['card']['billingDetails']['phone'] = (!empty($orderinfo->billing_phone_1))?$orderinfo->billing_phone_1:$orderinfo->billing_phone_2;
            }

            else
            {
                $config['postedParam']['email'] = $data['cko_cc_email'];
                $config['postedParam']['cardToken'] = $data['cko_cc_token'];

            }

            $respondCharge = $this->placeOrder($config);

            if($respondCharge->isValid()){
                if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {

                    $order->order_state_id = 1;
                    $order->order_state = JText::_('K2STORE_CONFIRMED');
                }

                else{

                    $errMsg = JText::_('K2STORE_CHECKOUTAPI_MESSAGE_TRANSACTION_UNSUCCESSFUL'). $respondCharge->getResponseMessage();
                    $order->order_state_id = 3;
                    $order->order_state = JText::_('K2STORE_DECLINED');
                    $errors[] = $errMsg;
                    $this->_log($errMsg, 'Error Message');  

                }

            }
            else{

                $errMsg = JText::_('K2STORE_CHECKOUTAPI_MESSAGE_TRANSACTION_UNSUCCESSFUL').$respondCharge->getExceptionState()->getErrorMessage();
                $order->order_state_id = 3;
                $order->order_state = JText::_('K2STORE_FAILED');
                $errors[] = $errMsg;
                $this->_log($errMsg, 'Error Message');
            }

            if(!$order->save()){
                    $errors[] = $order ->getError();
            }

            if(empty($errors)){
                $json['success']  = JText::_($this->params->get('onafterpayment', ''));
                $json['redirect'] = JRoute::_('index.php?option=com_k2store&view=checkout&task=confirmPayment&orderpayment_type='.$this->_element.'&paction=display');
                K2StoreHelperCart::removeOrderItems( $order->id );
            }
            
            if(count($errors)){
                $json['error'] = implode("\n", $errors);
            }          


        }

        return $json;


	}

	function _log($text, $type = 'message')
    {
		$file = JPATH_ROOT . "/cache/{$this->_element}.log";
		$date = JFactory::getDate();

		$f = fopen($file, 'a');
		fwrite($f, "\n\n" . $date->format('Y-m-d H:i:s'));
		fwrite($f, "\n" . $type . ': ' . $text);
		fclose($f);
    }

    function _getOrderInfo($orderpayment_id) {

    	$db = JFactory::getDBO();
    	$query = 'SELECT * FROM #__k2store_orderinfo WHERE orderpayment_id='.$db->quote($orderpayment_id);
    	$db->setQuery($query);
    	return $db->loadObject();
    }


	function getCurrency($order) {
    	$results = array();
    	$convert = false;
    	$params = JComponentHelper::getParams('com_k2store');
    	if( version_compare( $this->_j2version, '2.6.7', 'lt' ) ) {
    		$currency_code = $params->get('currency_code', 'USD');
    		$currency_value = 1;
    	} else {
    		$currency_code = $order->currency_code;
    		$currency_value = $order->currency_value;
    	}
    	$results['currency_code'] = $currency_code;
    	$results['currency_value'] = $currency_value;
    	$results['convert'] = $convert;

    	return $results;
    }

    function getAmount($value, $currency_code, $currency_value, $convert=false) {

    	if( version_compare( $this->_k2version, '2.6.7', 'lt' ) ) {
    		return K2StoreUtilities::number( $value, array( 'thousands'=>'', 'num_decimals'=>'2', 'decimal'=>'.') );
    	} else {
    		include_once (JPATH_ADMINISTRATOR.'/components/com_k2store/library/base.php');
    		$currencyObject = K2StoreFactory::getCurrencyObject();
    		$amount = $currencyObject->format($value, $currency_code, $currency_value, false);
    		return $amount;
    	}

    }

    function getVersion() {

        if(is_null($this->_k2version)) {
            $xmlfile = JPATH_ADMINISTRATOR.'/components/com_k2store/manifest.xml';
            $xml = JFactory::getXML($xmlfile);
            $this->_j2version=(string)$xml->version;
        }
        return $this->_j2version;
    }


    private function captureConfig()
    {
        $to_return['postedParam'] = array (
            'autoCapture' =>( $this->trans_type == 'Authorize_Capture'),
            'autoCapTime' => $this->auto_capture_time
        );

        return $to_return;
    }

    private function authorizeConfig()
    {
        $to_return['postedParam'] = array(
            'autoCapture' => ( $this->trans_type == 'Authorize'),
            'autoCapTime' => $this->auto_capture_time
        );
        return $to_return;
    }

    private function placeOrder($config)
    {
        $Api = CheckoutApi_Api::getApi(array('mode'=> $this->endpoint));

        return $Api->createCharge($config);

    }


}