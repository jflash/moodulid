<?php
/**
 * @package VirtueMart lisa
 * @author Matis Halmann - http://www.e-abi.ee/ (kuni 2014)
 * @author Joomla Eesti kogukond - http://www.eraser.ee/ (alates 2015)
 * @copyright (C) 2015- Joomla Eesti kogukond
 * @version 3.0
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

defined('_JEXEC') or die('Restricted access');
if (!class_exists('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}
class plgVmPaymentseb extends vmPSPlugin {
    // instance of class
    public static $_this = false;
    var $bank_down='seb';
    var $bank_UPPER='SEB';
    function __construct(& $subject, $config) {
	   parent::__construct($subject, $config);
	    $this->_loggable = true;
	    $this->tableFields = array_keys($this->getTableSQLFields());
	    $varsToPush = array('payment_currency' =>  array(0, 'int'),
        'status_success' => array(0,"int"),
        'status_canceled' => array(0,"int"),
        'payment_logos'          => array('', 'char'),
		    'priv_key' => array('', 'text'),
		    'pub_key' => array('', 'text'),
		    'VK_SND_ID' => array('', 'string'),
        'priv_pass' => array('', 'string'),
        'countries' => array('', 'string'),
        'return' =>array('','text'),
        'cancel' =>array('',"text"),
        'url' =>array('','text')
	    );
	    $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }
//     protected function getVmPluginCreateTableSQL() {
// 	   return $this->createTableSQL('Payment Seb table');
//     }
	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Payment Seb table');
	}

    function getTableSQLFields() {
    	$SQLfields = array(
    	    'id' => 'int(11) unsigned NOT NULL AUTO_INCREMENT',
    	    'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
			'order_number'			=> 'char(64)',
    	    'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
    	    'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
    	    'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
    	    'payment_currency' => 'char(3) ',
    	    'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
    	    'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
    	    'tax_id' => 'smallint(11) DEFAULT NULL'
    	);
    	return $SQLfields;
    }
    function plgVmConfirmedOrder($cart, $order) {
    	if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
    	    return null;
    	}
    	if (!$this->selectedThisElement($method->payment_element)) {
    	    return false;
    	}
      $cart->_dataValidated="1";
//     	$lang = JFactory::getLanguage();
//     	$filename = 'com_virtuemart';
//     	$lang->load($filename, JPATH_ADMINISTRATOR);
VmConfig::loadJLang('com_virtuemart',true);
VmConfig::loadJLang('com_virtuemart_orders', TRUE);
    	$vendorId = 0;
    	$html = "";
    	if (!class_exists('VirtueMartModelOrders'))
    	    require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
    	$this->getPaymentCurrency($method);
   	    $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
    	$db = JFactory::getDBO();
    	$db->setQuery($q);
    	$currency_code_3 = $db->loadResult();
    	$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
    	$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
    	$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
    	$this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
    	$dbValues['payment_name'] = $this->renderPluginName($method);
    	$dbValues['order_number'] = $order['details']['BT']->order_number;
    	$dbValues['VK_SND_ID'] = $method->VK_SND_ID;
    	$dbValues['payment_currency'] = $method->payment_currency;
    	$dbValues['pub_key'] = $method->pub_key;
    	$dbValues['priv_key'] = $method->priv_key;
      $dbValues['cancel']=$method->cancel;
      $dbValues['url']=$method->url;
      $dbValues['return']=$method->return;
    	$this->storePSPluginInternalData($dbValues);
    	$html = '<table>' . "\n";
    	$html .= $this->getHtmlRow('SEB_PAYMENT_INFO', $dbValues['payment_name']);
    	if (!empty($payment_info)) {
    	    $lang = JFactory::getLanguage();
    	   if ($lang->hasKey($method->payment_info)) {
    	       $payment_info = vmText::_($method->payment_info);
    	   } else {
    	        $payment_info = $method->payment_info;
    	   }
    	    $html .= $this->getHtmlRow($bank_UPPER._PAYMENTINFO, $payment_info);
    	}
    	if (!class_exists('VirtueMartModelCurrency')) {
    	    require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
          }
    	$currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);
    	$html .= $this->getHtmlRow('SEB_ORDER_NUMBER', $order['details']['BT']->order_number);
    	$html .= $this->getHtmlRow('SEB_AMOUNT', $currency->priceDisplay($order['details']['BT']->order_total));
    	$html .= '</table>' . "\n";
        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
    	$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total,false), 2);
        $bank_order_id= VirtueMartModelOrders::getOrderIdByOrderNumber($order['details']['BT']->order_number);
        $VK_a['VK_SERVICE'] = '1012';
        $VK_a['VK_VERSION'] = '008';
        $VK_a['VK_SND_ID'] = $dbValues['VK_SND_ID'];
        $VK_a['VK_STAMP'] = hexdec($bank_order_id);
        $VK_a['VK_AMOUNT'] = $totalInPaymentCurrency;
        $VK_a['VK_CURR'] = 'EUR';
        $VK_a['VK_REF'] = $this->generateRefNum($bank_order_id);
        $VK_a['VK_MSG'] = "Arve ". $bank_order_id. " tasumine";
        //$VK_a['VK_RETURN'] = JROUTE::_(JURI::root() . 'plugins/vmpayment/seb/bank.php');
        $VK_a['VK_RETURN'] = self::getSuccessUrl($order);
        //$VK_a['VK_CANCEL'] = $VK_a['VK_RETURN'];
        $VK_a['VK_CANCEL'] = self::getCancelUrl($order);
        $vkDateTime = new DateTime('NOW');
        $VK_a['VK_DATETIME'] = $vkDateTime->format(DateTime::ISO8601); //date("Y-m-d H:i:s", time()); //ISO-8601 
        $VK_a['VK_MAC'] = $this->createSignature($VK_a,$dbValues['priv_key'],$dbValues['priv_pass']);
        $VK_a['VK_ENCODING'] = 'UTF-8';
        $VK_a['VK_LANG'] = 'EST';
      //$VK_a['VK_ACC'] = '10002050618003'; //ainult SEB TEST-versiooni jaoks, aga pole vist üldse vaja
        $html.=( '<form method="post" action="'.$dbValues['url'].'">' );
        foreach( $VK_a as $VK_name => $VK_value ){
           $html.=( '<input type="hidden" name="' . htmlspecialchars($VK_name) . '" value="' . htmlspecialchars($VK_value) . '"/>'."\n"."\t" );
        }
        $html.=("</br>");
        $html.= ('<input type="submit" value="'.JText::_("VMPAYMENT_SEB_SUBMIT").'"/>' );
        $html.=( '</form>' );
		        $cart->emptyCart (); //teistel oli ka ja see pangavalikul kui lehte refreshitakse kustutab ostukaartilt kõik tooted
        		vRequest::setVar ('html', $html);
return TRUE;
//    	return $this->processConfirmedOrderPaymentResponse(true, $cart, $order, $html, , $method->status_canceled);
    }
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
   	if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
    	    return null; // Another method was selected, do nothing
    	}
      //VmConfig::loadJLang('com_virtuemart');
    	$db = JFactory::getDBO();
    	$q = 'SELECT * FROM `' . $this->_tablename . '` '
    		. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
    	$db->setQuery($q);
    	if (!($paymentTable = $db->loadObject())) {
//     	    vmWarn(500, $q . " " . $db->getErrorMsg());
//     	    return '';
    	}
    	$this->getPaymentCurrency($paymentTable);
    	$html = '<table class="adminlist table">' . "\n";
    	$html .=$this->getHtmlHeaderBE();
    	$html .= $this->getHtmlRowBE('SEB_PAYMENT_NAME', $paymentTable->payment_name);
    	$html .= '</table>' . "\n";
    	return $html;
    }
    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
    	return false;//set true to set payment cost to 1.00 EUR
    }
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
	   return $this->onStoreInstallPluginTable($jplugin_id);
    }
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {
	   return $this->OnSelectCheck($cart);
    }
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
	   return $this->displayListFE($cart, $selected, $htmlIn);
    }
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
	   return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }
    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
    	if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
    	    return null; // Another method was selected, do nothing
    	}
    	if (!$this->selectedThisElement($method->payment_element)) {
    	    return false;
    	}
    	 $this->getPaymentCurrency($method);
    	$paymentCurrencyId = $method->payment_currency;
      return; //teistel pole
    }
  function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}
    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
	   return $this->onShowOrderPrint($order_number, $method_id);
    }
    function plgVmDeclarePluginParamsPayment($name, $id, &$data) { //VM3 korral pole seda siia vaja
	   return $this->declarePluginParams('payment', $name, $id, $data);
    }
  function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}
    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
	   return $this->setOnTablePluginParams($name, $id, $table);
    }
    function createSignature( &$VK_a,$priv,$pass ){
      $data = $this->_composeData( $VK_a );
      $pkeyid = openssl_pkey_get_private( $priv,$pass);
      openssl_sign( $data, $signature, $pkeyid );
      $VK_MAC = base64_encode( $signature );
      openssl_free_key( $pkeyid );
      return $VK_MAC;
   }
   function _composeData( &$VK_a ){
      foreach( $VK_a as $data_bit ){
         $data.=str_pad( strlen( $data_bit ), 3, "0", STR_PAD_LEFT ) . $data_bit;
      }
      return $data;
   }
   function plgVmOnPaymentNotification(&$html) {
       return plgVmOnPaymentResponseReceived($html);
    }
    
    function plgVmOnPaymentResponseReceived(&$html) {
        //language
        if (!class_exists('VirtueMartCart'))
            require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		if (!class_exists ('shopFunctionsF')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php'); }
       	if (!class_exists('VirtueMartModelOrders'))
    	    require( VMPATH_ADMIN . DS . 'models' . DS . 'orders.php' );    
        $lang = JFactory::getLanguage();
    	$filename = 'com_virtuemart';
    	$lang->load($filename, JPATH_ADMINISTRATOR);
    	$vendorId = 0;
        $payment_data = JRequest::get('request');
        $order_id = substr($payment_data['VK_REF'], 0, -1);
        $pmethod=VirtueMartModelOrders::getOrder($order_id);
        $payment_method= $pmethod['details']['BT']->virtuemart_paymentmethod_id;
    	//if (!($this->_currentMethod = $this->getVmPluginMethod($payment_method))) { //korjan ringi
      if (!($method = $this->getVmPluginMethod($payment_method))) {
    	    return null; // Another method was selected, do nothing
    	}
    	//if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {  //korjan ringi
      if (!$this->selectedThisElement($method->payment_element)) {
    	    return null; //oli false
    	}

// 		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) { //lisasin selle ka kuna teistel on //korjan ringi
// 			return FALSE;
// 		}
    	
                
    	$payment_name = $this->renderPluginName($method);
        $vk_array=array();
        foreach ($payment_data as $key => $value) {
            if (substr($key, 0, 3) == "VK_") {
                $vk_array[$key]=$value;
            }
        }
        $sig_result = $this->signature_check($vk_array,$method->pub_key );
        $html = '<table>' . "\n";
            if($sig_result==0)
                $html .= $this->getHtmlRow('SEB_BANK_ERROR'," ").$sig_result;
            $html .= '</table>' . "\n";
            //$config = JFactory::getConfig();
            //$dbprefix =   $config->getValue( 'dbprefix' );
            $dbprefix = VmConfig::get('dbprefix',false); //oih ma ei tea kas eelmise kahe rea asemel nii sobib
             $order= new VirtueMartModelOrders();
             $order=$order->getOrder($order_id);
             $cart = VirtueMartCart::getCart();
            if($_REQUEST['VK_SERVICE']==1111 && $sig_result==1){
            $html .= $this->_getPaymentResponseHtml($payment_data, $payment_name);
                     if(isset($cart->_dataValidated)){
                         if($order['details']['BT']->order_status!=$method->status_success){
                            $this->update_status($method->status_success,$order_id,"VMPAYMENT_SEB_PAYMENT_CONFIRMED");
                        }
                        echo JText::_("VMPAYMENT_SEB_SUCCESS_MESSAGE");
                    }
            }elseif($_REQUEST['VK_SERVICE']==1911 && $sig_result==1){// makse on tühistatud
                if($order['details']['BT']->order_status!=$method->status_canceled){
            	   $this->update_status($method->status_canceled,$order_id,"VMPAYMENT_SEB_PAYMENT_CANCELED");
                }
                echo JText::_("VMPAYMENT_SEB_FAIL_MESSAGE");
            }
            $cart->emptyCart();
            $url=JROUTE::_(JURI::root() .'index.php?option=com_virtuemart&view=orders');
            echo '<head><meta http-equiv="Refresh" content="1;URL='.$url.'"></head>';
//exit;
$cart = VirtueMartCart::getCart ();
$cart->emptyCart ();
            return true;
   }
    protected function checkConditions($cart, $method, $cart_prices) {
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            return true;
        }
// 		if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) { //teistel on eelmine lõik nii, meil sellega ei toimi
// 			if ($amount_cond) {
// 				return TRUE;
// 			}
// 		}
        return false;
    }
    function update_status($status,$order_id,$text='VMPAYMENT_SEB_PAYMENT_CANCELED'){
        if ($order_id) {
	       // send the email only if payment has been accepted
	       if (!class_exists('VirtueMartModelOrders'))
		      require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
   	        $modelOrder = new VirtueMartModelOrders();
       	    $order['order_status'] = $status;
       	    $order['virtuemart_order_id'] = $order_id;
       	    $order['customer_notified'] =1;
   	        $order['comments'] = JTExt::sprintf($text, $order_id);
       	    $modelOrder->updateStatusForOneOrder($order_id, $order, true);
     	   }
    }
    function _getPaymentResponseHtml($bank_data, $payment_name) {
       	$html = '<table>' . "\n";
    	$html .= $this->getHtmlRow(SEB_PAYMENT_NAME, $payment_name);
        $stamp=dechex($bank_data['VK_STAMP']);//payment method number
    	$html .= $this->getHtmlRow(SEB_ORDER_NUMBER, $stamp);
    	$html .= $this->getHtmlRow(SEB_AMOUNT, $bank_data['VK_AMOUNT'] . " " . $bank_data['VK_CURR']);
    	$html .= '</table>' . "\n";
    	return $html;
    }
    function generateMACString($macFields) {
		$VK_variableOrder = Array(
		    1011 => Array(
				'VK_SERVICE','VK_VERSION','VK_SND_ID',
				'VK_STAMP','VK_AMOUNT','VK_CURR',
				'VK_ACC','VK_NAME','VK_REF','VK_MSG'
        //,'VK_RETURN', 'VK_CANCEL', 'VK_DATETIME' //ise lisatud
				),
				1012 => Array(
				'VK_SERVICE','VK_VERSION','VK_SND_ID',
				'VK_STAMP','VK_AMOUNT','VK_CURR',
				'VK_REF','VK_MSG'
				),
				1111 => Array(
				'VK_SERVICE','VK_VERSION','VK_SND_ID',
				'VK_REC_ID','VK_STAMP','VK_T_NO','VK_AMOUNT','VK_CURR',
				'VK_REC_ACC','VK_REC_NAME','VK_SND_ACC','VK_SND_NAME',
				'VK_REF','VK_MSG','VK_T_DATETIME'
				),
				1911 => Array(
				'VK_SERVICE','VK_VERSION','VK_SND_ID',
				'VK_REC_ID','VK_STAMP','VK_REF','VK_MSG'
				),
			);
		$requestNum = $macFields['VK_SERVICE'];
		$data = '';
		foreach ((array)$VK_variableOrder[$requestNum] as $kaey) {
			$v = $macFields[$kaey];
			$data .= str_pad (strlen ($v), 3, '0', STR_PAD_LEFT) . $v;
		}
		return $data;
	}
    function signature_check($VK_a,$pub_key){
        $VK_MAC = $VK_a['VK_MAC'];
        $signature = base64_decode( $VK_MAC );
        $data=$this->generateMACString($VK_a);
        $pubkey = openssl_get_publickey( $pub_key );
        //uuem$pubkey = openssl_pkey_get_public( $pub_key );
        $out = openssl_verify( $data, $signature, $pubkey );
        openssl_free_key( $pubkey );
        file_put_contents("001vkmac.txt", $VK_MAC.PHP_EOL, FILE_APPEND); //Joomla root kausta tuleb luu järgnevad viis tekstifaili
        file_put_contents("002sign.txt", $signature.PHP_EOL, FILE_APPEND);
        file_put_contents("003mac.txt", $data.PHP_EOL, FILE_APPEND);
        file_put_contents("004pubkey.txt", $pubkey.PHP_EOL, FILE_APPEND);
        file_put_contents("005out.txt", $out.PHP_EOL, FILE_APPEND);
        return $out;
    }
	function generateRefNum($stamp) {
		$chcs = array(7, 3, 1);
		$sum = 0;
		$pos = 0;
		for ($i = 0; $i < strlen($stamp); $i++) {
			$x = (int)(substr($stamp,strlen($stamp) - 1 - $i, 1));
			$sum = $sum + ($x * $chcs[$pos]);
			if ($pos == 2) {
				$pos = 0;
			} else {
				$pos = $pos + 1;
			}
		}
		$x = 10 - ($sum % 10);
		if ($x != 10) {
			$sum = $x;
		} else {
			$sum = 0;
		}
		return $stamp . $sum;
	}
  //siia lisan nüüd neid mida polnud
	function plgVmOnConfirmedOrderStorePaymentData ($virtuemart_order_id, $orderData, $priceData) {
		if (!$this->selectedThisPayment ($this->_pelement, $orderData->virtuemart_paymentmethod_id)) {
			return NULL; // Another method was selected, do nothing
		}
		return FALSE;
	}

	public function plgVmOnUpdateOrderPayment ($_formData) {
		return NULL;
	}

	public function plgVmOnUpdateOrderLine ($_formData) {
		return NULL;
	}

	public function plgVmOnEditOrderLineBE ($_orderId, $_lineId) {
		return NULL;
	}

	public function plgVmOnShowOrderLineFE ($_orderId, $_lineId) {
		return NULL;
	}

	protected function getLang () {
		$language =& JFactory::getLanguage ();
		$tag = strtolower (substr ($language->get ('tag'), 0, 2));
		return $tag;
	}

// sofort
	static function   getSuccessUrl ($order) {
		return JURI::root()."index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=" . $order['details']['BT']->virtuemart_paymentmethod_id . '&on=' . $order['details']['BT']->order_number . "&Itemid=" . vRequest::getInt('Itemid'). '&lang='.vRequest::getCmd('lang',''); ;
    //http://www.disalena.com/vm3/index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=4&on=39d1024&Itemid=124&lang=
	}
	static function   getCancelUrl ($order) {
		return  JURI::root()."index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&pm=" . $order['details']['BT']->virtuemart_paymentmethod_id . '&on=' . $order['details']['BT']->order_number . '&Itemid=' . vRequest::getInt('Itemid').'&lang='.vRequest::getCmd('lang','');
    //http://www.disalena.com/vm3/index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&pm=4&on=3982026&Itemid=124&lang=
	}
	static function   getNotificationUrl ($security, $order_number) {
		return JURI::root()  .  "index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&&security=" . $security . "&on=" . $order_number .'&lang='.vRequest::getCmd('lang','');
	}

}
