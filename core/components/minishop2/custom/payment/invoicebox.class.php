<?php

define('INVOICEBOX_API_KEY', 'oJaThirRcPAIx8WM3c3Vh2M4qNcenvLZ');  //Укажите секретный код из личного кабинета Invoicebox
define('INVOICEBOX_CURRENCY', 'RUR');  //Валюта
define('INVOICEBOX_IDENT', '78043');  //Региональный код магазина в Invoicebox из личного кабинета Invoicebox
define('INVOICEBOX_ID', '131');  //Идентификатор интернет-магазина в Invoicebox из личного кабинета Invoicebox
define('INVOICEBOX_TESTMODE', 1);  //Тестовый режим Invoicebox (Если 1 - деньги не будут списываться с карты)
define('INVOICEBOX_VAT', '18');  //Ставка налога
define('INVOICEBOX_SUCCESS_ID', 6); //Id страницы успешной оплаты
define('INVOICEBOX_CANCEL_ID', 6);  //Id страницы не успешной оплаты


if (!class_exists('msPaymentInterface')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/mspaymenthandler.class.php';
}

class InvoiceBox extends msPaymentHandler implements msPaymentInterface {

    /**
     * InvoiceBox constructor.
     *
     * @param xPDOObject $object
     * @param array $config
     */
    function __construct(xPDOObject $object, $config = array()) {
        parent::__construct($object, $config);

        $siteUrl = $this->modx->getOption('site_url');
        $assetsUrl = $this->modx->getOption('assets_url') . 'components/minishop2/';
        $postUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/invoiceboxpost.php';
        $paymentUrl = $siteUrl . substr($assetsUrl, 1) . 'payment/invoicebox.php';

        $this->config = array_merge(array(
            'paymentUrl' => $paymentUrl,
            'postUrl' => $postUrl,
            'itransfer_participant_id' => INVOICEBOX_ID,
            'itransfer_participant_ident' => INVOICEBOX_IDENT,
            'invoicebox_api_key' => INVOICEBOX_API_KEY,
            'invoicebox_currency' => INVOICEBOX_CURRENCY,
            'itransfer_testmode' => INVOICEBOX_TESTMODE,
            'itransfer_item_vatrate' => INVOICEBOX_VAT
                ), $config);
    }

    /**
     * @param msOrder $order
     *
     * @return array|string
     */
    public function send(msOrder $order) {
        if ($order->get('status') > 1) {
            return $this->error('ms2_err_status_wrong');
        }
        $total = number_format($order->get('cost'), 2, '.', '');

        $fullname = $_POST['receiver'];
        $email = $_POST['email'];
        $phone = $_POST["phone"];

        $signatureValue = md5(
                $this->config['itransfer_participant_id'] .
                $order->get('id') .
                $total .
                $this->config['invoicebox_currency'] .
                $this->config['invoicebox_api_key']
        );
        $success = $cancel = $this->modx->getOption('site_url');
        if ($id = INVOICEBOX_SUCCESS_ID) {
            $success = $this->modx->makeUrl($id, $context, $params, 'full');
        }
        if ($id = INVOICEBOX_CANCEL_ID) {
            $cancel = $this->modx->makeUrl($id, $context, $params, 'full');
        }
        $params = array(
            'itransfer_participant_id' => $this->config['itransfer_participant_id'],
            'itransfer_participant_ident' => $this->config['itransfer_participant_ident'],
            'itransfer_testmode' => $this->config['itransfer_testmode'],
            'itransfer_order_id' => $order->get('id'),
            'itransfer_body_type' => "PRIVATE",
            'CMS' => "MODX",
            'itransfer_participant_sign' => $signatureValue,
            'itransfer_order_currency_ident' => $this->config['invoicebox_currency'],
            'itransfer_person_name' => $fullname,
            'itransfer_person_email' => $email,
            'itransfer_person_phone' => $phone,
            'itransfer_order_description' => 'Оплата заказа ' . $order->get('id'),
            'itransfer_order_amount' => number_format($order->get('cost'), 2, '.', ''),
            'itransfer_url_notify' => $this->config['paymentUrl'],
            'itransfer_url_return' => $this->config['paymentUrl'] . '?action=success',
            'itransfer_url_cancel' => $this->config['paymentUrl'] . '?action=failure'
        );
        if (!empty($this->config['itransfer_testmode'])) {
            $params['itransfer_testmode'] = 1;
        } else {
            $params['itransfer_testmode'] = 0;
        }
        $i = 0;
        $products = $order->getMany('Products');
        $product_quantity = 0;
        foreach ($products as $item) {
            $name = $item->get('name');
            $i++;
            $product_quantity += $item->get('count');
            if (empty($name) && $product = $item->getOne('Product')) {
                $name = $product->get('pagetitle');
            }
            $params['itransfer_item' . $i . '_name'] = $name;
            $params['itransfer_item' . $i . '_price'] = number_format($item->get('price'), 2, '.', '');
            $params['itransfer_item' . $i . '_quantity'] = $item->get('count');
            $params['itransfer_item' . $i . '_measure'] = 'шт.';
            $params['itransfer_item' . $i . '_vatrate'] = $this->config['itransfer_item_vatrate'];
        }
        $params['itransfer_order_quantity'] = $product_quantity;
        if ($order->get('delivery_cost') > 0) {
            $i++;
            $params['itransfer_item' . $i . '_name'] = 'Доставка';
            $params['itransfer_item' . $i . '_price'] = number_format($order->get('delivery_cost'), 2, '.', '');
            $params['itransfer_item' . $i . '_quantity'] = 1;
            $params['itransfer_item' . $i . '_measure'] = 'шт.';
        }
        $link = $this->config['postUrl'] . '?' . http_build_query($params);
        return $this->success('', array('redirect' => $link));
    }

    /**
     * @param msOrder $order
     * @param array $params
     *
     * @return bool
     */
    public function receive(msOrder $order, $params = array()) {


        $participantId = IntVal($params['participantId']);
        $participantOrderId = IntVal($params['participantOrderId']);
        $ucode = $params['ucode'];
        $timetype = $params['timetype'];
        $time = $params['time'];
        $amount = $params['amount'];
        $currency = $params['currency'];
        $agentName = $params['agentName'];
        $agentPointName = $params['agentPointName'];
        $testMode = $params['testMode'];
        $sign = $params['sign'];

        $sign_strA = md5(
                $participantId .
                $participantOrderId .
                $ucode .
                $timetype .
                $time .
                $amount .
                $currency .
                $agentName .
                $agentPointName .
                $testMode .
                $this->config['invoicebox_api_key']);

        if ($sign != $sign_strA) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2:InvoiceBox] Could not finalize operation: sign key not valid');
            $this->ms2->changeOrderStatus($order->get('id'), 4);
            echo 'NotOK. sign key not valid';
        }

        if (number_format($order->get('cost'), 2, '.', '') != $amount) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[miniShop2:InvoiceBox] Could not finalize operation: amount not valid');
            $this->ms2->changeOrderStatus($order->get('id'), 4);
            echo 'NotOK. amount not valid';
        }

        $this->ms2->changeOrderStatus($order->get('id'), 2); 
        echo 'OK';
        return true;
    }

}
