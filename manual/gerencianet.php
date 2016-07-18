<?php

if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 * @version $Id: gerencianet.php,v 1.6 2016/08/31 11:00:57 ei
 *
 * a special type of 'cash on delivey':
 * @author Gerencianet
 * @version $Id: gerencianet.php 5122 2016-02-07 12:00:00Z joaoferreira $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2016 - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

include_once dirname(__FILE__) . '/lib/GerencianetIntegration.php';

class plgVmPaymentGerencianet extends vmPSPlugin {

    public static $_this = false;

    function __construct(& $subject, $config) {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush ();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        $this->domdocument = false;

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

        $lang = JFactory::getLanguage();
        $lang->load('plg_vmpayment_' . $this->_name, JPATH_ADMINISTRATOR);

        if (!class_exists('CurrencyDisplay'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php' );
        
    }

    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Gerencianet Table');
    }

    function getTableSQLFields() {
        $SQLfields = array(
            'id' => 'bigint(15) unsigned NOT NULL AUTO_INCREMENT',
            'gerencianet_charge_id' => 'char(32) DEFAULT NULL',
            'gerencianet_charge_type' => 'char(32) DEFAULT NULL',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'gerencianet_status'  => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'payment_currency' => 'char(3) ',
            'billet_discount' => ' decimal(10,2) DEFAULT NULL ',
            'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
            'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
            );
        return $SQLfields;
    }

    function getPluginParams(){
        $db = JFactory::getDbo();
        $sql = "select virtuemart_paymentmethod_id from #__virtuemart_paymentmethods where payment_element = 'gerencianet'";
        $db->setQuery($sql);
        $id = (int)$db->loadResult();
        return $this->getVmPluginMethod($id);
    }

    function plgVmConfirmedOrder($cart, $order) {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->order_id = $order['details']['BT']->order_number;
        $url  = JURI::root();

        $doc = & JFactory::getDocument();
        $url_lib      = $url. DS .'plugins'. DS .'vmpayment'. DS .'gerencianet'.DS;
        $url_js       = $url_lib . 'assets'. DS. 'js'. DS;
        $this->url_imagens  = $url_lib . 'imagens'. DS;
        $url_css      = $url_lib . 'assets'. DS. 'css'. DS;

        $url_redireciona_gerencianet = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm='.$order['details']['BT']->virtuemart_paymentmethod_id);
        $url_pedidos = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=orders');

        $billet_option = $method->billet_option;
        $card_option = $method->card_option;
        $payee_code = $method->payee_code;

        $doc->addCustomTag('
            <script language="javascript">
            jQuery.noConflict();
            var redireciona_gerencianet = "'.$url_redireciona_gerencianet.'";
            var url_pedidos = "'.$url_pedidos.'";
        </script>
        <script type="text/javascript" data-billet_active="'.$billet_option.'" data-card_active="'.$card_option.'" data-payee_code="'.$payee_code.'" data-shop_url="' . JRoute::_(JURI::root() . '/index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&gn=ajax&pm='.$order['details']['BT']->virtuemart_paymentmethod_id) . '&format=raw"  language="javascript" src="'.$url_js.'gerencianet-checkout.js"></script>
        <script type="text/javascript" language="javascript" src="'.$url_js.'jquery.maskedinput.js"></script>
        <link href="'.$url_css.'gerencianet-checkout.css" rel="stylesheet" type="text/css"/>
        ');

        $lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;

        $this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
        $html = "";

        if (!class_exists('VirtueMartModelOrders')) {
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        }

        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
        $dbValues['payment_name'] = 'Gerencianet';

        $html = '<table>' . "\n";
        $html .= $this->getHtmlRowBE('GERENCIANET_TYPE_TRANSACTION', $dbValues['payment_name']);

        if (!class_exists('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }

        $currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);
        $html .= $this->getHtmlRowBE('GERENCIANET_ORDER_NUMBER', $order['details']['BT']->order_number);
        $html .= $this->getHtmlRowBE('GERENCIANET_AMOUNT', GerencianetIntegration::formatCurrencyBRL(intval($order['details']["BT"]->order_total*100))) . '</table>' . "\n";

        $this->_virtuemart_paymentmethod_id  = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['order_number']            = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction']      = $method->cost_per_transaction;
        $dbValues['cost_percent_total']        = $method->cost_percent_total;
        $dbValues['payment_order_total']       = $totalInPaymentCurrency;

        $this->storePSPluginInternalData($dbValues);

        $html .= $this->gerencianetGenerateForm($method, $order);
        return $this->processConfirmedOrderPaymentResponse(1, $cart, $order, $html, $dbValues['payment_name'], $novo_status);

    }

    public function gerencianetGenerateForm($method, $order) {

        $doc =& JFactory::getDocument();
        $doc->addScript($this->url_js);

        $order_total = intval(round($order['details']["BT"]->order_total,2)*100);

        $sandbox = $method->sandbox;

        $db = JFactory::getDBO();
        $query_shipping = 'SELECT `order_shipment` FROM ' . '#__virtuemart_orders' . " WHERE  `virtuemart_order_id`= '" . $order['details']["BT"]->virtuemart_order_id . "'";

        $db->setQuery($query_shipping);
        $exibe = $db->loadObjectList();

        $shipping = 0;
        foreach ( $exibe as $result) {
            {
                $shipping_cost = (int)(((Float)$result->order_shipment)*100);
                if ($shipping_cost > 0)
                {
                    $shipping = (int) $shipping_cost;
                } else {
                    $shipping = 0;
                }
            }
        }
        
        $discount = floatval(preg_replace( '/[^0-9.]/', '', str_replace(",",".",$method->billet_discount)));
        $discount_value = intval(($order_total-$shipping)*($discount/100));
        $discount_formatted =  str_replace(",",".",$discount);

        $billet_option = $method->billet_option;
        $card_option = $method->card_option;
        $order_total_billet = $order_total-$discount_value;
        $order_total_card =$order_total;
        $order_billet_discount=GerencianetIntegration::formatCurrencyBRL($discount_value);
        $order_total_with_billet_discount = GerencianetIntegration::formatCurrencyBRL($order_total-$discount_value);

        $gn_warning_sandbox_message = JText::_('VMPAYMENT_GERENCIANET_SANDBOX_MODE_ACTIVE_NOTIFICATION');
        $gn_mininum_gn_charge_price = JText::_('VMPAYMENT_GERENCIANET_MINIMUM_VALUE');
        $gn_billet_payment_method_comments = JText::_('VMPAYMENT_GERENCIANET_BILLET_COMMENTS');
        $gn_cnpj_option = JText::_('VMPAYMENT_GERENCIANET_CNPJ_OPTION');
        $gn_cnpj = JText::_('VMPAYMENT_GERENCIANET_CNPJ');
        $gn_corporate_name = JText::_('VMPAYMENT_GERENCIANET_RAZAO_SOCIAL');
        $gn_name = JText::_('VMPAYMENT_GERENCIANET_NAME');
        $gn_email = JText::_('VMPAYMENT_GERENCIANET_EMAIL');
        $gn_cpf = JText::_('VMPAYMENT_GERENCIANET_CPF');
        $gn_phone = JText::_('VMPAYMENT_GERENCIANET_TELEFONE');
        $gn_birth = JText::_('VMPAYMENT_GERENCIANET_BIRTH');
        $gn_billing_address_title = JText::_('VMPAYMENT_GERENCIANET_ADDRESS_TITLE');
        $gn_street = JText::_('VMPAYMENT_GERENCIANET_STREET');
        $gn_street_number = JText::_('VMPAYMENT_GERENCIANET_STREET_NUMBER');
        $gn_neighborhood = JText::_('VMPAYMENT_GERENCIANET_NEIGHBORHOOD');
        $gn_address_complement = JText::_('VMPAYMENT_GERENCIANET_ADDRESS_COMPLEMENT');
        $gn_cep = JText::_('VMPAYMENT_GERENCIANET_CEP');
        $gn_city = JText::_('VMPAYMENT_GERENCIANET_CITY');
        $gn_state = JText::_('VMPAYMENT_GERENCIANET_STATE');
        $gn_card_brand = JText::_('VMPAYMENT_GERENCIANET_CARD_BRAND');
        $gn_card_number = JText::_('VMPAYMENT_GERENCIANET_CARD_NUMBER');
        $gn_card_cvv = JText::_('VMPAYMENT_GERENCIANET_CARD_CVV');
        $gn_card_cvv_tip = JText::_('VMPAYMENT_GERENCIANET_CARD_CVV_TIP');
        $gn_card_expiration = JText::_('VMPAYMENT_GERENCIANET_CARD_EXPIRATION');
        $gn_card_installments_options = JText::_('VMPAYMENT_GERENCIANET_CARD_INSTALLMENTS_OPTIONS');
        $gn_card_brand_select = JText::_('VMPAYMENT_GERENCIANET_CARD_BRAND_SELECT');
        $gn_card_payment_comments = JText::_('VMPAYMENT_GERENCIANET_CARD_COMMENTS');

        $campo_bairro = $method->campo_bairro;
        $campo_numero = $method->campo_numero;
        $campo_complemento = $method->campo_complemento;
        $campo_logradouro = $method->campo_logradouro;
        $campo_data_nascimento = $method->campo_data_nascimento;

        $order_number = $order['details']["BT"]->order_number;
        $customer_name = $order["details"]["BT"]->first_name .' '. $order["details"]["BT"]->last_name;
        $order_email = $order["details"]["BT"]->email;
        $customer_number = $order["details"]["BT"]->virtuemart_user_id;
        $customer_address = $order["details"]["BT"]->$campo_logradouro;
        $customer_address_number = $order["details"]["BT"]->$campo_numero;
        $customer_address_complemento = $order["details"]["BT"]->$campo_complemento;
        $customer_bairro = $order["details"]["BT"]->$campo_bairro;
        $customer_data_nascimento = $order["details"]["BT"]->$campo_data_nascimento;
        $customer_city = $order["details"]["BT"]->city;
        $customer_state = ShopFunctions::getStateByID($order["details"]["BT"]->virtuemart_state_id, "state_2_code");

        $customer_phone = $order["details"]["BT"]->phone_1;
        $replacements = array(" ", "-", "(",")");
        $customer_phone = str_replace($replacements, "", $customer_phone);
        $customer_phone = '('.substr($customer_phone,0,2).')'.substr($customer_phone,2,4).'-'.substr($customer_phone,6,4);

        $customer_zip = $order["details"]["BT"]->zip;
        $replacements = array(" ", ".", ",", "-", ";");
        $customer_zip = str_replace($replacements, "", $customer_zip);
        $customer_zip = substr($customer_zip,0,5).'-'.substr($customer_zip,5,3);

        $billet_discount_formatted = str_replace(".",",",$discount);

        $gnIntegration = $this->configGnIntegration($order['details']['BT']->virtuemart_paymentmethod_id);

        $max_installments = $gnIntegration->max_installments(intval($order_total));

        $url  = JURI::root();

        $url_lib      = $url. DS .'plugins'. DS .'vmpayment'. DS .'gerencianet'.DS;

        $conteudo = '
        <div id="gerencianet-container">
        <input type="hidden" id="order_total" name="order_total" value="'.$order_total.'" />
        <input type="hidden" id="order_number" name="order_number" value="'.$order_number.'" />
        ';
        if ($sandbox == "1") {
            $conteudo .= '<div class="gn-osc-alert-payment" id="wc-gerencianet-messages-sandbox">
                <div>'.$gn_warning_sandbox_message.'</div>
            </div>';
        }

        $conteudo .= '<div class="gn-osc-warning-payment" id="wc-gerencianet-messages">';
        if (($card_option && $order_total_card<500) && ($billet_option && $order_total_billet<500)) {
            $conteudo .= '<div>'.$gn_mininum_gn_charge_price.'</div>';
        }
        $conteudo .= '</div>';

        $conteudo .= '<div style="margin: 0px;">';
        if ($billet_option=="1") {
            $conteudo .= '<div id="gn-billet-payment-option" class="gn-osc-payment-option gn-osc-payment-option-selected">
                <div>
                    <div id="billet-radio-button" class="gn-osc-left">
                        <input type="radio" name="paymentMethodRadio" id="paymentMethodBilletRadio" class="gn-osc-radio" value="billet" checked="true" />
                    </div>
                    <div class="gn-osc-left gn-osc-icon-gerencianet">
                        <span class="gn-icon-icones-personalizados_boleto"></span>
                    </div>
                    <div class="gn-osc-left gn-osc-payment-option-gerencianet">
                        <strong>Boleto Bancário</strong>';
                        if ($discount>0) {
                            $conteudo .= '<span style="font-size: 14px; line-height: 15px;"><br>+'.$billet_discount_formatted.'% de desconto</span>';
                        }
                    $conteudo .= '</div>
                    <div class="gn-osc-left gn-osc-payment-option-sizer"></div>
                    <div class="clear"></div>
                </div>
            </div>';
        }
        if ($card_option=="1") {
            $conteudo .= '
            <div id="gn-card-payment-option" class="gn-osc-payment-option gn-osc-payment-option-unselected">
                <div>
                    <div id="card-radio-button" class="gn-osc-left">
                        <input type="radio" name="paymentMethodRadio" id="paymentMethodCardRadio" class="gn-osc-radio" value="card" />
                    </div>
                    <div class="gn-osc-left gn-osc-icon-gerencianet">
                        <span class="gn-icon-credit-card2"></span>
                    </div>
                    <div class="gn-osc-left gn-osc-payment-option-gerencianet">
                        <strong>Cartão de Crédito</strong>
                        <span style="font-size: 14px; line-height: 15px;"><br>em até '.$max_installments.'</span>
                    </div>
                    <div class="gn-osc-left gn-osc-payment-option-sizer"></div>
                    <div class="clear"></div>
                </div>
            </div>';
        }
        $conteudo .= '
            <div class="clear"></div>
        </div>';
        if ($billet_option=="1") {
        $conteudo .= '<div id="collapse-payment-billet" class="gn-osc-background" >
          <div class="panel-body">
              <div class="gn-osc-row gn-osc-pay-comments">
                  <p class="gn-left-space-2"><strong>'.$gn_billet_payment_method_comments.'</strong></p>
              </div>
              <div class="gn-form">
                <div id="billet-data">
                    <div style="background-color: #F3F3F3; border: 1px solid #F3F3F3; margin-top: 10px; margin-bottom: 10px;">
                  <div class="gn-osc-row">
                    <div class="gn-col-12 gn-cnpj-row">
                    <input type="checkbox" name="pay_billet_with_cnpj" id="pay_billet_with_cnpj" value="1" />'.$gn_cnpj_option.'
                    </div>
                  </div>

                  <div id="pay_cnpj" class="required gn-osc-row">
                    <div class="gn-col-2 gn-label">
                      <label for="gn_billet_cnpj" class="gn-right-padding-1">'.$gn_cnpj.'</label>
                    </div>
                    <div class="gn-col-10">
                      
                      <div>
                        <div class="gn-col-3 required">
                          <input type="text" name="gn_billet_cnpj" id="gn_billet_cnpj" class="form-control cnpj-mask" value="" />
                        </div>
                        <div class="gn-col-8">
                          <div class="required">
                            <div class="gn-col-4 gn-label">
                              <label class=" gn-col-12 gn-right-padding-1" for="gn_billet_corporate_name">'.$gn_corporate_name.'</label>
                            </div>
                            <div class="gn-col-8">
                              <input type="text" name="gn_billet_corporate_name" id="gn_billet_corporate_name" class="form-control" value="" />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  </div>

                  <div id="gn_name_row" class="required gn-osc-row gn-billet-field" >
                    <div class="gn-col-2 gn-label">
                      <label for="gn_billet_full_name" class="gn-right-padding-1">'.$gn_name.'</label>
                    </div>
                    <div class="gn-col-10">
                      <input type="text" name="gn_billet_full_name" id="gn_billet_full_name" value="'.$customer_name.'" class="form-control" />
                    </div>
                  </div>


                  <div id="gn_email_row" class=" required gn-osc-row gn-billet-field" >
                    <div class="gn-col-2 gn-label">
                      <label class="gn-col-12 gn-right-padding-1" for="gn_billet_email">'.$gn_email.'</label>
                    </div>
                    <div class="gn-col-10">
                      <input type="text" name="gn_billet_email" value="'.$order_email.'" id="gn_billet_email" class="form-control" />
                    </div>
                  </div>

                  <div id="gn_cpf_phone_row" class="required gn-osc-row gn-billet-field" >
                    <div class="gn-col-2 gn-label">
                      <label for="gn_billet_cpf" class="gn-right-padding-1">'.$gn_cpf.'</label>
                    </div>
                    <div class="gn-col-10">
                      
                      <div>
                        <div class="gn-col-3 required">
                          <input type="text" name="gn_billet_cpf" id="gn_billet_cpf" value="" class="form-control cpf-mask" />
                        </div>
                        <div class="gn-col-8">
                          <div class=" required">
                            <div class="gn-col-4 gn-label">
                            <label class="gn-col-12 gn-right-padding-1" for="gn_billet_phone_number" >'.$gn_phone.'</label>
                            </div>
                            <div class="gn-col-4">
                              <input type="text" name="gn_billet_phone_number" id="gn_billet_phone_number" value="'.$customer_phone.'" class="form-control phone-mask" />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
                </div>

            </div>

            <div class="gn-osc-row" style="padding: 20px;">';
                if ($discount>0) {
                $conteudo .= '<div class="gn-osc-row" style="border: 1px solid #DEDEDE; border-bottom: 0px; margin: 0px; padding:5px;">
                    <div style="float: left;">
                        <strong>DESCONTO DE '.$discount_formatted.'% NO BOLETO:</strong>
                    </div>
                    <div style="float: right;">
                        <strong>-'.$order_billet_discount.'</strong>
                    </div>
                </div>';
                }
                $conteudo .= '<div class="gn-osc-row" style="border: 1px solid #DEDEDE; margin: 0px; padding:5px;">
                    <div style="float: left;">
                        <strong>TOTAL:</strong>
                    </div>
                    <div style="float: right;">
                        <strong>'.$order_total_with_billet_discount.'</strong>
                    </div>
                </div>
            </div>
            <div class="gn-osc-row">
                <div style="float: right;">
                    <button id="gn-pay-billet-button" class="gn-osc-button">Pagar com Boleto Bancário</button>
                </div>
                <div class="pull-right gn-loading-request">
                    <div class="gn-loading-request-row">
                      <div class="pull-left gn-loading-request-text">
                        Autorizando, aguarde...
                      </div>
                      <div class="pull-left gn-icons">
                        <div class="spin gn-loading-request-spin-box icon-gerencianet"><div class="gn-icon-spinner6 gn-loading-request-spin-icon"></div></div>
                      </div>
                    </div>
                </div>
                <div class="clear"></div>
            </div>
          </div>';
        }

        if ($card_option=="1") {
            $conteudo .= '<div id="collapse-payment-card" class="panel-collapse';
            if ($billet_option=="1") {
                $conteudo .= 'gn-hide';
            }
            $conteudo .= ' gn-osc-background" >
            <div class="panel-body">
            <div class="gn-osc-row gn-osc-pay-comments">
               <p class="gn-left-space-2"><strong>'.$gn_card_payment_comments.'</strong></p>
            </div>

            <div class="gn-form">
                <div id="card-data" >
                    <div style="background-color: #F3F3F3; border: 1px solid #F3F3F3; margin-top: 10px; margin-bottom: 10px;">
                    <div class="gn-osc-row">
                      <div class="gn-col-12 gn-cnpj-row">
                        <input type="checkbox" name="pay_card_with_cnpj" id="pay_card_with_cnpj" value="1" /> '.$gn_cnpj_option.'
                      </div>
                    </div>

                    <div id="pay_cnpj_card" class=" required gn-osc-row" >
                      <div class="gn-col-2 gn-label">
                      <label class="gn-right-padding-1" for="gn_card_cnpj">'.$gn_cnpj.'</label>
                      </div>
                      <div class="gn-col-10">
                        
                        <div>
                          <div class="gn-col-3 required">
                            <input type="text" name="gn_card_cnpj" id="gn_card_cnpj" class="form-control cnpj-mask" value="" />
                          </div>
                          <div class="gn-col-8">
                            <div class=" required gn-left-space-2">
                              <div class="gn-col-4 gn-label">
                                <label class="gn-col-12 gn-right-padding-1" for="gn_card_corporate_name">'.$gn_corporate_name.'</label>
                              </div>
                              <div class="gn-col-8">
                                <input type="text" name="gn_card_corporate_name" id="gn_card_corporate_name" class="form-control" value="" />
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    </div>

                    <div id="gn_card_name_row" class="required gn-osc-row gn-card-field" >
                      <div class="gn-col-2 gn-label">
                        <label class="gn-col-12 gn-right-padding-1" for="gn_card_full_name">'.$gn_name.'</label>
                      </div>
                      <div class="gn-col-10">
                        <input type="text" name="gn_card_full_name" id="gn_card_full_name" value="'.$customer_name.'" class="form-control" />
                      </div>
                    </div>

                    <div id="gn_card_cpf_phone_row" class="required gn-osc-row gn-card-field" >
                    
                        <div class="gn-col-2 gn-label">
                            <label for="gn_card_cpf" class="gn-right-padding-1" >'.$gn_cpf.'</label>
                        </div>
                        <div class="gn-col-4">
                            <input type="text" name="gn_card_cpf" id="gn_card_cpf" value="" class="form-control cpf-mask gn-minimum-size-field" />
                        </div>
                        <div class="gn-col-6">
                          <div class="gn-col-4 gn-label">
                              <label class="gn-left-space-2 gn-right-padding-1" for="gn_card_phone_number">'.$gn_phone.'</label>
                          </div>
                          <div class="gn-col-8">
                              <input type="text" name="gn_card_phone_number" value="'.$customer_phone.'" id="gn_card_phone_number" class="form-control phone-mask gn-minimum-size-field" />
                          </div>
                          
                        </div>
                    </div>

                    <div id="gn_card_birth_row" class=" required gn-osc-row gn-card-field" >
                      <div class="gn-col-3 gn-label-birth">
                          <label class="gn-right-padding-1" for="gn_card_birth">'.$gn_birth.'</label>
                      </div>
                      <div class="gn-col-3">
                          <input type="text" name="gn_card_birth" id="gn_card_birth" value="'.$customer_data_nascimento.'" class="form-control birth-mask" />
                      </div>
                    </div>

                    <div id="gn_card_email_row" class=" required gn-card-field" >
                      <div class="gn-col-2">
                        <label class="gn-col-12 gn-label gn-right-padding-1" for="gn_card_email">'.$gn_email.'</label>
                      </div>
                      <div class="gn-col-10">
                        <input type="text" name="gn_card_email" value="'.$order_email.'" id="gn_card_email" class="form-control" />
                      </div>
                    </div>
                    <div class="clear"></div>

                    <div id="billing-adress" class="gn-section">
                        <div class="gn-osc-row gn-card-field">
                            <p>
                            <strong>'.$gn_billing_address_title.'</strong>
                            </p>
                        </div>

                        <div id="gn_card_street_number_row" class="required gn-osc-row gn-card-field" >
                            <div class="gn-col-2">
                                <label class="gn-col-12 gn-label gn-right-padding-1" for="gn_card_street">'.$gn_street.'</label>
                            </div>
                            
                            <div class="gn-col-10">
                                <div class="gn-col-6 required">
                                    <input type="text" name="gn_card_street" id="gn_card_street" value="'.$customer_address.'" class="form-control" />
                                </div>
                                <div class="gn-col-6">
                                    <div class=" required gn-left-space-2">
                                        <div class="gn-col-5">
                                            <label class="gn-col-12 gn-label gn-right-padding-1" for="gn_card_street_number">'.$gn_street_number.'</label>
                                        </div>
                                        <div class="gn-col-7">
                                            <input type="text" name="gn_card_street_number" id="gn_card_street_number" value="'.$customer_address_number.'" class="form-control" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="gn_card_neighborhood_row" class="gn-osc-row gn-card-field">
                            <div class="gn-col-2 required">
                                <label class="gn-col-12 gn-label required gn-right-padding-1" for="gn_card_neighborhood">'.$gn_neighborhood.'</label>
                            </div>
                    
                            <div class="gn-col-3">
                                
                                <input type="text" name="gn_card_neighborhood" id="gn_card_neighborhood" value="'.$customer_bairro.'" class="form-control" />
                            </div>
                            <div class="gn-col-7">
                                <div class=" gn-left-space-2">
                                  <div class="gn-col-5">
                                  <label class="gn-col-12 gn-label gn-right-padding-1" for="gn_card_complement">'.$gn_address_complement.'</label>
                                  </div>
                                  <div class="gn-col-7">
                                    <input type="text" name="gn_card_complement" id="gn_card_complement" value="'.$customer_address_complemento.'" class="form-control" maxlength="54" />
                                  </div>
                                </div>
                            </div>
                        </div>

                        <div id="gn_card_city_zipcode_row" class="required billing-address-data gn-card-field gn-osc-row" >
                            <div class="gn-col-2">
                                <label class="gn-col-12 gn-label gn-right-padding-1" for="gn_card_zipcode">'.$gn_cep.'</label>
                            </div>
                            <div class="gn-col-10">
                                <div class="gn-col-4 required">
                                    <input type="text" name="gn_card_zipcode" id="gn_card_zipcode" value="'.$customer_zip.'" class="form-control" />
                                </div>
                                <div class="gn-col-8">
                                    <div class=" required gn-left-space-2">
                                      <div class="gn-col-4">
                                          <label class="gn-col-12 gn-label gn-right-padding-1" for="gn_card_city">'.$gn_city.'</label>
                                      </div>
                                      <div class="gn-col-6">
                                        <input type="text" name="gn_card_city" id="gn_card_city" value="'.$customer_city.'" class="form-control" />
                                      </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="gn_card_state_row" class="required billing-address-data gn-card-field gn-osc-row" >
                          <div class="gn-col-2">
                            <label class="gn-col-12 gn-label gn-right-padding-1" for="gn_card_state">'.$gn_state.'</label>
                          </div>
                          <div class="gn-col-10">
                            <select name="gn_card_state" id="gn_card_state" class="form-control gn-form-select">
                              <option value=""></option> 
                              <option value="AC" '; if ($customer_state=="AC") { $conteudo .= 'selected'; } $conteudo .= '>Acre</option> 
                              <option value="AL" '; if ($customer_state=="AL") { $conteudo .= 'selected'; } $conteudo .= '>Alagoas</option> 
                              <option value="AP" '; if ($customer_state=="AP") { $conteudo .= 'selected'; } $conteudo .= '>Amapá</option> 
                              <option value="AM" '; if ($customer_state=="AM") { $conteudo .= 'selected'; } $conteudo .= '>Amazonas</option> 
                              <option value="BA" '; if ($customer_state=="BA") { $conteudo .= 'selected'; } $conteudo .= '>Bahia</option> 
                              <option value="CE" '; if ($customer_state=="CE") { $conteudo .= 'selected'; } $conteudo .= '>Ceará</option> 
                              <option value="DF" '; if ($customer_state=="DF") { $conteudo .= 'selected'; } $conteudo .= '>Distrito Federal</option> 
                              <option value="ES" '; if ($customer_state=="ES") { $conteudo .= 'selected'; } $conteudo .= '>Espírito Santo</option> 
                              <option value="GO" '; if ($customer_state=="GO") { $conteudo .= 'selected'; } $conteudo .= '>Goiás</option> 
                              <option value="MA" '; if ($customer_state=="MA") { $conteudo .= 'selected'; } $conteudo .= '>Maranhão</option> 
                              <option value="MT" '; if ($customer_state=="MT") { $conteudo .= 'selected'; } $conteudo .= '>Mato Grosso</option> 
                              <option value="MS" '; if ($customer_state=="MS") { $conteudo .= 'selected'; } $conteudo .= '>Mato Grosso do Sul</option> 
                              <option value="MG" '; if ($customer_state=="MG") { $conteudo .= 'selected'; } $conteudo .= '>Minas Gerais</option> 
                              <option value="PA" '; if ($customer_state=="PA") { $conteudo .= 'selected'; } $conteudo .= '>Pará</option> 
                              <option value="PB" '; if ($customer_state=="PB") { $conteudo .= 'selected'; } $conteudo .= '>Paraíba</option> 
                              <option value="PR" '; if ($customer_state=="PR") { $conteudo .= 'selected'; } $conteudo .= '>Paraná</option> 
                              <option value="PE" '; if ($customer_state=="PE") { $conteudo .= 'selected'; } $conteudo .= '>Pernambuco</option> 
                              <option value="PI" '; if ($customer_state=="PI") { $conteudo .= 'selected'; } $conteudo .= '>Piauí</option> 
                              <option value="RJ" '; if ($customer_state=="RJ") { $conteudo .= 'selected'; } $conteudo .= '>Rio de Janeiro</option> 
                              <option value="RN" '; if ($customer_state=="RN") { $conteudo .= 'selected'; } $conteudo .= '>Rio Grande do Norte</option> 
                              <option value="RS" '; if ($customer_state=="RS") { $conteudo .= 'selected'; } $conteudo .= '>Rio Grande do Sul</option> 
                              <option value="RO" '; if ($customer_state=="RO") { $conteudo .= 'selected'; } $conteudo .= '>Rondônia</option> 
                              <option value="RR" '; if ($customer_state=="RR") { $conteudo .= 'selected'; } $conteudo .= '>Roraima</option> 
                              <option value="SC" '; if ($customer_state=="SC") { $conteudo .= 'selected'; } $conteudo .= '>Santa Catarina</option> 
                              <option value="SP" '; if ($customer_state=="SP") { $conteudo .= 'selected'; } $conteudo .= '>São Paulo</option> 
                              <option value="SE" '; if ($customer_state=="SE") { $conteudo .= 'selected'; } $conteudo .= '>Sergipe</option> 
                              <option value="TO" '; if ($customer_state=="TO") { $conteudo .= 'selected'; } $conteudo .= '>Tocantins</option> 
                            </select>
                          </div>
                        </div>
                    </div>
                    <div class="clear"></div>

                    <div class="gn-section" style="background-color: #F0F0F0; padding: 5px 10px;">
                        <div class="required gn-osc-row">
                            <div>
                            <label class="" for="gn_card_brand">'.$gn_card_brand.'</label>
                            </div>
                            <div>
                                <div class="gn-card-brand-selector">
                                    <input id="none" type="radio" name="gn_card_brand" id="gn_card_brand" value="" checked class="gn-hide" />
                                    <div class="pull-left gn-card-brand-content">
                                        <input id="visa" type="radio" name="gn_card_brand" id="gn_card_brand" value="visa" class="gn-hide" />
                                        <label class="gn-card-brand gn-visa" for="visa"></label>
                                    </div>
                                    <div class="pull-left gn-card-brand-content">
                                        <input id="mastercard" type="radio" name="gn_card_brand" id="gn_card_brand" value="mastercard" class="gn-hide" />
                                        <label class="gn-card-brand gn-mastercard" for="mastercard"></label>
                                    </div>
                                    <div class="pull-left gn-card-brand-content">
                                        <input id="amex" type="radio" name="gn_card_brand" id="gn_card_brand" value="amex" class="gn-hide" />
                                        <label class="gn-card-brand gn-amex" for="amex"></label>
                                    </div>
                                    <div class="pull-left gn-card-brand-content">
                                        <input id="diners" type="radio" name="gn_card_brand" id="gn_card_brand" value="diners" class="gn-hide" />
                                        <label class="gn-card-brand gn-diners" for="diners"></label>
                                    </div>
                                    <div class="pull-left gn-card-brand-content">
                                        <input id="discover" type="radio" name="gn_card_brand" id="gn_card_brand" value="discover" class="gn-hide" />
                                        <label class="gn-card-brand gn-discover" for="discover"></label>
                                    </div>
                                    <div class="pull-left gn-card-brand-content">
                                        <input id="jcb" type="radio" name="gn_card_brand" id="gn_card_brand" value="jcb" class="gn-hide" />
                                        <label class="gn-card-brand gn-jcb" for="jcb"></label>
                                    </div>
                                    <div class="pull-left gn-card-brand-content">
                                        <input id="elo" type="radio" name="gn_card_brand" id="gn_card_brand" value="elo" class="gn-hide" />
                                        <label class="gn-card-brand gn-elo" for="elo"></label>
                                    </div>
                                    <div class="pull-left gn-card-brand-content">
                                        <input id="aura" type="radio" name="gn_card_brand" id="gn_card_brand" value="aura" class="gn-hide" />
                                        <label class="gn-card-brand gn-aura" for="aura"></label>
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </div>
                        </div>

                        <div class="gn-osc-row required">
                                <div class="gn-col-6">
                                    <div>
                                        '.$gn_card_number.'
                                    </div>
                                    <div>
                                        <div class="gn-card-number-input-row" style="margin-right: 20px;">
                                            <input type="text" name="gn_card_number_card" id="gn_card_number_card" value="" class="form-control gn-input-card-number" />
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                </div>
                                
                                <div class="gn-col-6">
                                    <div>
                                        '.$gn_card_cvv.'
                                    </div>
                                    <div>
                                        <div class="pull-left gn-cvv-row">
                                            <input type="text" name="gn_card_cvv" id="gn_card_cvv" value="" class="form-control gn-cvv-input" />
                                        </div>
                                        <div class="pull-left">
                                            <div class="gn-cvv-info">
                                                <div class="pull-left gn-icon-card-input">
                                                </div>
                                                <div class="pull-left">
                                                    '.$gn_card_cvv_tip.'
                                                </div>
                                                <div class="clear"></div>
                                            </div>
                                        </div>
                                        <div class="clear"></div>
                                    </div>
                                </div>
                                <div class="clear"></div>
                                <input type="hidden" name="gn_card_payment_token" id="gn_card_payment_token" value="" />
                        </div>

                        <div class="gn-osc-row">
                            <div class="gn-col-12">
                                    <div>   
                                        '.$gn_card_expiration.'
                                    </div>
                                    <div class="gn-card-expiration-row">
                                        <select class="form-control gn-card-expiration-select" name="gn_card_expiration_month" id="gn_card_expiration_month" >
                                            <option value=""> MM </option>
                                            <option value="01"> 01 </option>
                                            <option value="02"> 02 </option>
                                            <option value="03"> 03 </option>
                                            <option value="04"> 04 </option>
                                            <option value="05"> 05 </option>
                                            <option value="06"> 06 </option>
                                            <option value="07"> 07 </option>
                                            <option value="08"> 08 </option>
                                            <option value="09"> 09 </option>
                                            <option value="10"> 10 </option>
                                            <option value="11"> 11 </option>
                                            <option value="12"> 12 </option>
                                        </select>
                                        <div class="gn-card-expiration-divisor">
                                            /
                                        </div>
                                        <select class="form-control gn-card-expiration-select" name="gn_card_expiration_year" id="gn_card_expiration_year" >
                                            <option value=""> AAAA </option>';
                                            
                                            $actual_year = intval(date("Y")); 
                                            $last_year = $actual_year + 15;
                                            for ($i = $actual_year; $i <= $last_year; $i++) {
                                                $conteudo .= '<option value="'.$i.'"> '.$i.' </option>';
                                            }
                                            
                                        $conteudo .= '</select>
                                        <div class="clear"></div>
                                    </div>
                                </div>

                        </div>

                        <div class="gn-osc-row required">
                            <div class="gn-col-12">
                                <label class="" for="gn_card_installments">'.$gn_card_installments_options.'</label>
                            </div>
                            <div class="gn-col-12">
                                <select name="gn_card_installments" id="gn_card_installments" class="form-control gn-form-select">
                                    <option value="">'.$gn_card_brand_select.'</option> 
                                </select>
                            </div>
                            <div class="clear"></div>
                        </div>
                    </div>
              </div>
            </div>
        </div>
        <div class="gn-osc-row" style="padding: 20px;">
            <div class="gn-osc-row" style="border: 1px solid #DEDEDE; margin: 0px; padding:5px;">
                <div style="float: left;">
                    <strong>TOTAL:</strong>
                </div>
                <div style="float: right;">
                    <strong>'.GerencianetIntegration::formatCurrencyBRL($order_total).'</strong>
                </div>
            </div>
        </div>
        <div class="gn-osc-row">
            <div style="float: right;">
                <button id="gn-pay-card-button" class="gn-osc-button">Pagar com Cartão de Crédito</button>
            </div>
            <div class="pull-right gn-loading-request">
                <div class="gn-loading-request-row">
                  <div class="pull-left gn-loading-request-text">
                    Autorizando, aguarde...
                  </div>
                  <div class="pull-left gn-icons">
                    <div class="spin gn-loading-request-spin-box icon-gerencianet"><div class="gn-icon-spinner6 gn-loading-request-spin-icon"></div></div>
                  </div>
                </div>
            </div>
            <div class="clear"></div>
        </div>

      </div>';
      }

        $conteudo .= "</div>";

        $conteudo .= '<div id="gn-payment-success" class="gn-success-content gn-hide">
          <div class="gn-payment-finalized">
          <h1>Pagamento realizado com sucesso.</h1>

          <div>
            <div class="gn-success-payment">
                <div class="row gn-box-emission">
                    <div class="pull-left gn-left-space-2">
                        <img src="' . $url_lib . 'assets/images/gerencianet-configurations.png" alt="Gerencianet" title="Gerencianet" />
                    </div>
                    <div class="pull-left gn-title-emission gn-billet-pay-info">
                        Boleto emitido pela Gerencianet
                    </div>

                    <div class="pull-left gn-title-emission gn-card-pay-info">
                        O processamento do seu cartão de crédito está sendo realizado por nós. Aguarde.
                    </div>
                </div>

                <div class="gn-success-payment-inside-box">
                    <div class="row" style="display: inline-block;">
                        <div style="float:left; width: 15%; margin-left: 20px;">
                          <div class="gn-icon-emission-success">
                              <span class="gn-icon-check-circle-o"></span>
                          </div>
                        </div>

                        <div class="gn-success-payment-billet-comments gn-billet-pay-info" style="float:left; width:70%;">
                            
                            O Boleto Bancário foi gerado com sucesso. Efetue o pagamento em qualquer banco conveniado, lotéricas, correios ou bankline. Fique atento à data de vencimento do boleto.
                            
                            <p>Número da Cobrança: <b><span class="charge_id_success" style="width: 100px;"></span></b>
                            </p>
                        </div>

                        <div class="gn-success-payment-billet-comments gn-card-pay-info" style="float:left; width:70%;">

                                A cobrança em seu cartão está sendo processada. Assim que houver a confirmação, enviaremos um e-mail para o endereço <b>'.$order_email.'</b>, informado em seu cadastro. Caso não receba o produto ou serviço adquirido, você tem o prazo de <b>14 dias a partir da data de confirmação do pagamento</b> para abrir uma contestação.<br>Informe-se em  <a href="http://www.gerencianet.com.br/contestacao" target="_blank">www.gerencianet.com.br/contestacao</a>.
                        
                            <p>Número da Cobrança: <b><span class="charge_id_success" style="width: 100px;"></span></b>
                            </p>
                        </div>

                    </div>

                    <div class="row gn-billet-pay-info">
                        <div class="buttons gn-align-center">
                            <a id="button-payment-billet" class="gn-osc-button" href="" target="_blank" >
                                Visualizar Boleto
                            </a>
                        </div>
                    </div>
                </div>
              </div>
          </div>
          </p>
          </div>';

        return $conteudo;
    }

    public function updateOrderStatusGN($virtuemart_order_id, $order_id) {

        $db = JFactory::getDBO();
        $query = 'SELECT payment_name, payment_order_total, payment_currency, virtuemart_paymentmethod_id
        FROM `' . $this->_tablename . '`
        WHERE order_number = "'.$order_id.'"';
        $db->setQuery($query);
        $pagamento = $db->loadObjectList();

        $response_fields = array();
        $response_fields['virtuemart_order_id']   = $virtuemart_order_id;
        $response_fields['type_transaction']    = 'gn type';
        $response_fields['status']            = 'status';
        $response_fields['msg_status']        = 'message';
        $response_fields['order_number']      = $order_id;

        $response_fields['payment_name']              = $pagamento[0]->payment_name;
        $response_fields['payment_currency']            = $pagamento[0]->payment_currency;
        $response_fields['payment_order_total']           = $pagamento[0]->payment_order_total;
        $response_fields['virtuemart_paymentmethod_id']   = $pagamento[0]->virtuemart_paymentmethod_id;

        $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);

        return true;
    }

    /**
     * Display stored payment data for an order
     *
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return null; // Another method was selected, do nothing
        }

        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
        . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);
        if (!($paymentTable = $db->loadObject())) {
            vmWarn(500, $q . " " . $db->getErrorMsg());
            return '';
        }


        $html = '<table class="adminlist">' . "\n";
        $html .=$this->getHtmlHeaderBE();

        $html .= $this->getHtmlRowBE('GERENCIANET_PAYMENT_NAME', 'Gerencianet');
        $html .= $this->getHtmlRowBE('GERENCIANET_PAYMENT_DATE', $paymentTable->modified_on);
        $html .= $this->getHtmlRowBE('GERENCIANET_CODIGO_GERENCIANET', $paymentTable->gerencianet_charge_id);
        $html .= $this->getHtmlRowBE('GERENCIANET_STATUS', $paymentTable->gerencianet_status);
        $html .= $this->getHtmlRowBE('GERENCIANET_DISCOUNT', GerencianetIntegration::formatCurrencyBRL(intval($paymentTable->billet_discount*100)));
        $html .= $this->getHtmlRowBE('GERENCIANET_TOTAL_CURRENCY', GerencianetIntegration::formatCurrencyBRL(intval($paymentTable->payment_order_total*100)));
        $html .= $this->getHtmlRowBE('GERENCIANET_TYPE_TRANSACTION', $paymentTable->gerencianet_charge_type);
        $html .= '</table>' . "\n";
        return $html;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    function setCartPrices (VirtueMartCart $cart, &$cart_prices, $method) {
        return parent::setCartPrices($cart, $cart_prices, $method);
    }

    protected function checkConditions($cart, $method, $cart_prices) {

        return true;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
      $mainframe = JFactory::getApplication();
      if($mainframe->isAdmin()) {
           return;
       }

       if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
          return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement ($method->payment_element)) {
          return FALSE;
        }
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    function configGnIntegration($pm) {
        $method = $this->getVmPluginMethod($pm);

        if ($method->sandbox=="1") {
            $sandbox = "yes";
        } else {
            $sandbox = "no";
        }
        
        return new GerencianetIntegration($method->client_id_production,$method->client_secret_production,$method->client_id_development,$method->client_secret_development,$sandbox,$method->payee_code);
    }

    function gerencianetRequest($pm) {
        $method = $this->getVmPluginMethod($pm);
        $action = JRequest::getVar('action');

        switch ($action) {

            case 'get_installments':
                
                $gnIntegration = $this->configGnIntegration($pm);

                $post_brand = JRequest::getVar('brand');
                if ($post_brand=="") {
                    $post_brand = 'visa';
                }

                $totalOrder = JRequest::getVar('value');
                $total = intval($totalOrder);
                $brand = $post_brand;
                $gnApiResult = $gnIntegration->get_installments($total,$brand);

                $resultCheck = array();
                $resultCheck = json_decode($gnApiResult, true);

                echo $gnApiResult;
                break;

            case 'create_charge':

                $gnIntegration = $this->configGnIntegration($pm);  

                $post_order_id = JRequest::getVar('order_id');
                $cart = VirtueMartCart::getCart();
                $items = array();

                $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($post_order_id);

                if (!class_exists('VirtueMartModelOrders'))
                   require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

                $db = JFactory::getDBO();
                $query = 'SELECT * FROM ' . '#__virtuemart_order_items' . " WHERE  `virtuemart_order_id`= '" . $virtuemart_order_id . "'";

                $db->setQuery($query);
                $exibe = $db->loadObjectList();

                foreach ( $exibe as $result) {
                    {
                        $items [] = array (
                            'name' => $result->order_item_name,
                            'value' => (int) ((Float)$result->product_final_price*100),
                            'amount' => (int) $result->product_quantity
                            );
                    }
                }

                $db = JFactory::getDBO();
                $query_shipping = 'SELECT `order_shipment` FROM ' . '#__virtuemart_orders' . " WHERE  `virtuemart_order_id`= '" . $virtuemart_order_id . "'";

                $db->setQuery($query_shipping);
                $exibe = $db->loadObjectList();

                $shipping=null;
                foreach ( $exibe as $result) {
                    {
                        $shipping_cost = (int)(((Float)$result->order_shipment)*100);
                        if ($shipping_cost > 0)
                        {
                            $shipping = array (
                                    array (
                                        'name' => 'Custo de Envio',
                                        'value' => (int) $shipping_cost
                                    )
                                );
                        } else {
                            $shipping=null;
                        }
                    }
                }

                $notificationURL = JRoute::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&format=raw&pm='.$pm);

                $gnApiResult =  $gnIntegration->create_charge($post_order_id,$items,$shipping,$notificationURL);

                $resultCheck = array();
                $resultCheck = json_decode($gnApiResult, true);

                echo $gnApiResult;

                break;

            case 'pay_billet':

                if ($method->billet_expiration) {
                    $billetExpireDays = intval($method->billet_expiration);
                } else {
                    $billetExpireDays = "5";
                }
                $expirationDate = date("Y-m-d", mktime (0, 0, 0, date("m")  , date("d")+intval($billetExpireDays), date("Y")));

                $post_order_id = JRequest::getVar('order_id');
                $post_pay_billet_with_cnpj = JRequest::getVar('pay_billet_with_cnpj');
                $post_corporate_name = JRequest::getVar('corporate_name');
                $post_cnpj = JRequest::getVar('cnpj');
                $post_name = JRequest::getVar('name');
                $post_cpf = JRequest::getVar('cpf');
                $post_phone_number = JRequest::getVar('phone_number');
                $post_charge_id = JRequest::getVar('charge_id');

                if (!class_exists('VirtueMartModelOrders'))
                   require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

                $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($post_order_id);

                if ($post_pay_billet_with_cnpj=="1") {
                    $juridical_data = array (
                      'corporate_name' => $post_corporate_name,
                      'cnpj' => $post_cnpj
                    );

                    $customer = array (
                        'name' => $post_name,
                        'cpf' => $post_cpf,
                        'phone_number' => $post_phone_number,
                        'juridical_person' => $juridical_data
                    );
                } else {
                    $customer = array (
                        'name' => $post_name,
                        'cpf' => $post_cpf,
                        'phone_number' => $post_phone_number
                    );
                }

                $db_total = JFactory::getDBO();
                $query = 'SELECT order_salesPrice FROM `#__virtuemart_orders` WHERE virtuemart_order_id = "'.$virtuemart_order_id.'"';
                $db_total->setQuery($query);
                $total_order = $db_total->loadObjectList();

                $discount = floatval(preg_replace( '/[^0-9.]/', '', str_replace(",",".",$method->billet_discount)));
                $discountTotalValue = intval(($total_order[0]->order_salesPrice)*($discount));

                if ($discountTotalValue>0) {
                    $discount = array (
                        'type' => 'currency',
                        'value' => $discountTotalValue
                    );
                } else {
                    $discount=null;
                }
                
                $gnIntegration = $this->configGnIntegration($pm);  
                $gnApiResult = $gnIntegration->pay_billet($post_charge_id,$expirationDate,$customer,$discount);

                $resultCheck = array();
                $resultCheck = json_decode($gnApiResult, true);

                $db = JFactory::getDBO();
                $query = 'SELECT payment_name, payment_order_total, payment_currency, virtuemart_paymentmethod_id
                FROM `' . $this->_tablename . '`
                WHERE order_number = "'.$post_order_id.'"';
                $db->setQuery($query);
                $pagamento = $db->loadObjectList();
                $response_fields = array();
                $response_fields['virtuemart_order_id']   = $virtuemart_order_id;
                $response_fields['gerencianet_charge_id']    = $post_charge_id;
                $response_fields['gerencianet_charge_type']      = JText::_('VMPAYMENT_GERENCIANET_BILLET');
                $response_fields['gerencianet_status']        = JText::_('VMPAYMENT_GERENCIANET_STATUS_WAITING_PAYMENT');
                $response_fields['order_number']      = $post_order_id;
                $response_fields['billet_discount']      = ($discountTotalValue/100);

                $response_fields['payment_name']              = $pagamento[0]->payment_name;
                $response_fields['payment_currency']            = $pagamento[0]->payment_currency;
                $response_fields['payment_order_total']           = $pagamento[0]->payment_order_total-($discountTotalValue/100);
                $response_fields['virtuemart_paymentmethod_id']   = $pagamento[0]->virtuemart_paymentmethod_id;

                $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);

                echo $gnApiResult;

                break;

            case 'pay_card':

                $post_order_id = JRequest::getVar('order_id');
                if (JRequest::getVar('pay_card_with_cnpj')) {
                    $post_pay_card_with_cnpj = JRequest::getVar('pay_card_with_cnpj');
                }
                if (JRequest::getVar('corporate_name')) {
                    $post_corporate_name = JRequest::getVar('corporate_name');
                }
                if (JRequest::getVar('cnpj')) {
                    $post_cnpj = JRequest::getVar('cnpj');
                }
                
                $post_name = JRequest::getVar('name');
                $post_cpf = JRequest::getVar('cpf');
                $post_phone_number = JRequest::getVar('phone_number');
                $post_email = JRequest::getVar('email');
                $post_birth = JRequest::getVar('birth');
                $post_street = JRequest::getVar('street');
                $post_number = JRequest::getVar('number');
                $post_neighborhood = JRequest::getVar('neighborhood');
                $post_zipcode = preg_replace( '/[^0-9]/', '', JRequest::getVar('zipcode'));
                $post_city = JRequest::getVar('city');
                $post_state = JRequest::getVar('state');
                $post_complement = JRequest::getVar('complement');
                $post_payment_token = JRequest::getVar('payment_token');
                $post_installments = JRequest::getVar('installments');
                $post_charge_id = JRequest::getVar('charge_id');

                if ($post_pay_card_with_cnpj=="1") {
                    $juridical_data = array (
                      'corporate_name' => $post_corporate_name,
                      'cnpj' => $post_cnpj
                    );

                    $customer = array (
                        'name' => $post_name,
                        'cpf' => $post_cpf,
                        'phone_number' => $post_phone_number,
                        'juridical_person' => $juridical_data,
                        'email' => $post_email,
                        'birth' => $post_birth
                    );
                } else {
                    $customer = array (
                        'name' => $post_name,
                        'cpf' => $post_cpf,
                        'phone_number' => $post_phone_number,
                        'email' => $post_email,
                        'birth' => $post_birth
                    );
                }

                $billingAddress = array (
                    'street' => $post_street,
                    'number' => $post_number,
                    'neighborhood' => $post_neighborhood,
                    'zipcode' => $post_zipcode,
                    'city' => $post_city,
                    'state' => $post_state,
                    'complement' => $post_complement
                );

                $discountTotalValue=0;

                if ($discountTotalValue>0) {
                    $discount = array (
                        'type' => 'currency',
                        'value' => $discountTotalValue
                    );
                } else {
                    $discount=null;
                }

                $gnIntegration = $this->configGnIntegration($pm);
                $gnApiResult = $gnIntegration->pay_card((int)$post_charge_id,$post_payment_token,(int)$post_installments,$billingAddress,$customer,$discount);

                $resultCheck = array();
                $resultCheck = json_decode($gnApiResult, true);

                $db = JFactory::getDBO();
                $query = 'SELECT payment_name, payment_order_total, payment_currency, virtuemart_paymentmethod_id
                FROM `' . $this->_tablename . '`
                WHERE order_number = "'.$post_order_id.'"';
                $db->setQuery($query);
                $pagamento = $db->loadObjectList();
                $response_fields = array();
                $response_fields['virtuemart_order_id']   = $virtuemart_order_id;
                $response_fields['gerencianet_charge_id']    = $post_charge_id;
                $response_fields['gerencianet_charge_type']      = JText::_('VMPAYMENT_GERENCIANET_CREDIT_CARD');
                $response_fields['gerencianet_status']        = JText::_('VMPAYMENT_GERENCIANET_STATUS_WAITING_PAYMENT');
                $response_fields['order_number']      = $post_order_id;
                $response_fields['billet_discount']      = ($discountTotalValue/100);

                $response_fields['payment_name']              = $pagamento[0]->payment_name;
                $response_fields['payment_currency']            = $pagamento[0]->payment_currency;
                $response_fields['payment_order_total']           = $pagamento[0]->payment_order_total-($discountTotalValue/100);
                $response_fields['virtuemart_paymentmethod_id']   = $pagamento[0]->virtuemart_paymentmethod_id;

                $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);

                echo $gnApiResult;

                break;

            default:
                break;

        }
    }

    function plgVmOnPaymentNotification() {

        $pm = JRequest::getVar('pm');
        $gn = JRequest::getVar('gn');
        $notification_token = JRequest::getVar('notification');

        if ($gn=="ajax") {
            $this->gerencianetRequest($pm);
            return null;
        }

        if (isset($notification_token)) {


            if (!class_exists('VirtueMartCart'))
                require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
            if (!class_exists('shopFunctionsF'))
                require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
            if (!class_exists('VirtueMartModelOrders'))
                require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

            $gnIntegration = $this->configGnIntegration($pm);  
            $notification = json_decode($gnIntegration->notificationCheck($notification_token));
            if ($notification->code==200) {


                foreach ($notification->data as $notification_data) {
                    $orderIdFromNotification = $notification_data->custom_id;
                    $orderStatusFromNotification = $notification_data->status->current;
                }

                $order_number = $orderIdFromNotification;

                $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);

                $vendorId = 0;
                $payment = $this->getDataByOrderId($virtuemart_order_id);

                $method = $this->getVmPluginMethod($pm);
                if (!$this->selectedThisElement($method->payment_element)) {
                    return false;
                }

                $new_status=null;

                switch($orderStatusFromNotification) {
                    case 'waiting':
                        $new_status = $method->status_cob_waiting;
                        $mensagem = JText::_('VMPAYMENT_GERENCIANET_CHARGE_STATUS_WAITING');
                        break;
                    case 'paid':
                        $new_status = $method->status_cob_paid;
                        $mensagem = JText::_('VMPAYMENT_GERENCIANET_CHARGE_STATUS_PAID');

                        $db_check_billet_discount = JFactory::getDbo();
                        $query = 'SELECT billet_discount FROM `' . $this->_tablename . '` WHERE virtuemart_order_id = "'.$virtuemart_order_id.'"';
                        $db_check_billet_discount->setQuery($query);
                        $charge_data = $db_check_billet_discount->loadObjectList();
                        $total_billet_discount = $charge_data[0]->billet_discount;

                        if ($total_billet_discount>0) {
                            $db = JFactory::getDbo();
                            $query = 'SELECT order_discount, order_total FROM `#__virtuemart_orders` WHERE virtuemart_order_id = "'.$virtuemart_order_id.'"';
                            $db->setQuery($query);
                            $pagamento = $db->loadObjectList();
                            $descontoGn = -$total_billet_discount;
                            $novoTotal = $pagamento[0]->order_total+$descontoGn;
                            $totalDesconto = $pagamento[0]->order_discount+$descontoGn;

                            $db_update = JFactory::getDbo();

                            $sql = "UPDATE `#__virtuemart_orders` SET `order_discount` = '".$totalDesconto."', `order_total` = '".$novoTotal."'  WHERE `virtuemart_order_id` = ".$virtuemart_order_id;
                            $db_update->setQuery($sql);
                            $db_update->query();
                        }
                        break;
                    case 'unpaid':
                        $new_status = $method->status_cob_unpaid;
                        $mensagem = JText::_('VMPAYMENT_GERENCIANET_CHARGE_STATUS_UNPAID');
                        break;
                    case 'refunded':
                        $new_status = $method->status_cob_refunded;
                        $mensagem = JText::_('VMPAYMENT_GERENCIANET_CHARGE_STATUS_REFUNDED');
                        break;
                    case 'contested':
                        $new_status = $method->status_cob_contested;
                        $mensagem = JText::_('VMPAYMENT_GERENCIANET_CHARGE_STATUS_CONTESTED');
                        break;
                    case 'canceled':
                        $new_status = $method->status_cob_canceled;
                        $mensagem = JText::_('VMPAYMENT_GERENCIANET_CHARGE_STATUS_CANCELED');
                        break;
                    default:
                        $new_status=null;
                        break;
                }

                if ($virtuemart_order_id && $new_status!=null) {
                    if (!class_exists('VirtueMartModelOrders'))
                        require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );

                    $db = JFactory::getDBO();
                    $query = 'SELECT * FROM `' . $this->_tablename . '` WHERE virtuemart_order_id = "'.$virtuemart_order_id.'"';
                    $db->setQuery($query);
                    $pagamento = $db->loadObjectList();

                    $response_fields = array();
                    $response_fields['virtuemart_order_id']         = $virtuemart_order_id;
                    $response_fields['gerencianet_charge_id']       = $pagamento[0]->gerencianet_charge_id;
                    $response_fields['gerencianet_charge_type']     = $pagamento[0]->gerencianet_charge_type;
                    $response_fields['gerencianet_status']          = $mensagem;
                    $response_fields['order_number']                = $pagamento[0]->order_number;
                    $response_fields['billet_discount']             = $pagamento[0]->billet_discount;

                    $response_fields['payment_name']                = $pagamento[0]->payment_name;
                    $response_fields['payment_currency']            = $pagamento[0]->payment_currency;
                    $response_fields['payment_order_total']         = $pagamento[0]->payment_order_total;
                    $response_fields['virtuemart_paymentmethod_id'] = $pagamento[0]->virtuemart_paymentmethod_id;

                    $this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);

                    $modelOrder = new VirtueMartModelOrders();
                    $orderitems = $modelOrder->getOrder($virtuemart_order_id);
                    $nb_history = count($orderitems['history']);
                    $order = array();
                    $order['order_status']      = $new_status;
                    $order['virtuemart_order_id']   = $virtuemart_order_id;
                    $order['comments']        = $mensagem;
                    $order['customer_notified']   = 1;
                    
                    $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

                    return TRUE;
                }

            }

        }
        return true;
    }   

    function plgVmOnPaymentResponseReceived(&$html='') {
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return true;
    }

    public function plgVmOnUserPaymentCancel() {
        return true;
    }

    function plgVmDeclarePluginParamsPaymentVM3($data) {
        return $this->declarePluginParams('payment', $data);
    }

}
