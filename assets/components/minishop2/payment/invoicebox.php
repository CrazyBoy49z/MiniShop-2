<?php

if (!isset($modx)) {
	define('MODX_API_MODE', true);
	require dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';

	$modx->getService('error', 'error.modError');
}

$modx->error->message = null;
/** @var modX $modx */
$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');
		
$log = print_r($_POST,1)." \n";
        file_put_contents(dirname(__FILE__)."/invoicebox_log.log", $log, FILE_APPEND);	
/** @var miniShop2 $miniShop2 */
$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');
if (!class_exists('Invoicebox')) {
	exit('Error: could not load payment class "Invoicebox".');
}
$context = '';
$params = array();
/** @var msOrder $order */
$order = $modx->newObject('msOrder');
/** @var msPaymentInterface|Invoicebox $handler */
$handler = new Invoicebox($order);

if (!empty($_REQUEST['sign']) && !empty($_REQUEST['participantOrderId']) && empty($_REQUEST['action'])) {
    if ($order = $modx->getObject('msOrder', $_REQUEST['participantOrderId'])) {
        $handler->receive($order, $_REQUEST);
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR,
            '[miniShop2:InvoiceBox] Could not retrieve order with id ' . $_REQUEST['participantOrderId']);
    }
}

if (!empty($_REQUEST['participantOrderId'])) {
    $params['msorder'] = $_REQUEST['participantOrderId'];
}

$success = $failure = $modx->getOption('site_url');
if ($id = $modx->getOption('ms2_payment_invoicebox_success_id', null, 0)) {
    $success = $modx->makeUrl($id, $context, $params, 'full');
}
if ($id = $modx->getOption('ms2_payment_invoicebox_cancel_id', null, 0)) {
    $failure = $modx->makeUrl($id, $context, $params, 'full');
}

$redirect = !empty($_REQUEST['action']) && $_REQUEST['action'] == 'success'
    ? $success
    : $failure;
header('Location: ' . $redirect);

