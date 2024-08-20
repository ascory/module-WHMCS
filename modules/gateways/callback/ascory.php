<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Billing\Invoice;
use WHMCS\Database\Capsule;

$allowedIPs = ['193.222.99.133'];
$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : $_SERVER['REMOTE_ADDR'];
if (!in_array($ip, $allowedIPs)) {
    die('IP адрес не в белом списке, доступ запрещен!');
}

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

$data = json_decode(file_get_contents('php://input'), true);
if (empty($data['data']) || empty($data['data']['amount']) || empty($data['data']['id']) || empty($data['data']['comment']) || empty($data['data']['type'])) {
    logActivity("Недостаточно данных для обработки платежа");
    die('Недостаточно данных для обработки платежа');
}

$data2 = $data['data'];
$amount = $data2['amount'];
$id = $data2['comment'];
$status = $data2['type'];

if ($status !== 'success') {
    logActivity("Ошибка при подтверждении платежа для заказа ID: $id");
    logTransaction($gatewayParams['name'], "Операция провалена. ID счета: $id. Сумма платежа: $amount. Статус платежа: $status", 'Failure');
    die('Операция провалена');
}

$invoice = Capsule::table('tblinvoices')->where('id', $id)->first();
if (!$invoice) {
    logActivity("Ошибка при подтверждении платежа для заказа ID: $id");
    logTransaction($gatewayParams['name'], "Счет не найден в системе. ID счета от Ascory: $id. Сумма платежа: $amount. Статус платежа: $status", 'Failure');
    die('Счет не найден в системе');
}

if ($invoice->status != 'Paid') {
    $invoiceId = $invoice->id;
    $transactionDescription = "Получен платеж через {$gatewayParams['name']} с id {$id}";
    $userId = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->value('userid');
    addTransaction($userId, 1, $transactionDescription, $amount, 0, $invoiceId, $id, $gatewayParams['paymentmethod']);
    addInvoicePayment($invoiceId, $id, $amount, 0, $gatewayParams['paymentmethod']);
    Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->update([
            'status' => 'Paid',
            'datepaid' => date('Y-m-d H:i:s'),
        ]);
    logTransaction($gatewayParams['name'], "Успешная оплата через {$gatewayParams['name']}. ID счета: $id. Сумма платежа: $amount. Статус платежа: $status", 'Success');
    header('HTTP/1.1 200 OK');
    echo json_encode(['code' => 200]);
} else {
    logTransaction($gatewayParams['name'], "Счет уже оплачен. ID счета: $id. Сумма платежа: $amount. Статус платежа: $status", 'Failure');
    die('Счет уже оплачен');
}
?>
