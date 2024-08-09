<?php
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Billing\Invoice;
use WHMCS\Database\Capsule;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$allowedIPs = ['193.222.99.133'];
$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0] : $_SERVER['REMOTE_ADDR'];
if (!in_array($ip, $allowedIPs)) {
    die('IP адрес не в вайтлисте, доступ запрещен!');
}

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

$data = json_decode(file_get_contents('php://input'), true);
$data2 = $data['data'];
if (empty($data2['amount']) || empty($data2['id']) || empty($data['hash'])) {
    logActivity("Недостаточно данных для обработки платеж");
    logTransaction($gatewayParams['name'], 'Недостаточно данных для обработки платежа', 'Failure');
    die('Недостаточно данных для обработки платежа');
}

$amount = $data['amount'];
$shop = $gatewayParams['shop'];
$id = $data2['comment'];
$hash = $data['hash'];
$status = $data2['type'];

$hashString = $shop . json_encode($data2) . $gatewayParams['key1'] . $gatewayParams['key2'];
if (!password_verify($hashString, $hash)) {
	die('Ошибка проверки хеша');
}

if ($status !== 'success') {
    logActivity("Ошибка при подтверждении платежа для заказа ID: $id");
    logTransaction($gatewayParams['name'], 'Операция провалена. ID счета ' . $id . '. Сумма платежа:' . $amount . '. Статус платежа: ' . $status, 'Failure');
    die('Операция провалена');
}
$invoice = Capsule::table('tblinvoices')->where('id', $id)->first();
if (!$invoice) {
    logActivity("Ошибка при подтверждении платежа для заказа ID: $id");
    logTransaction($gatewayParams['name'], 'Счет не найден в системе. ID счета: ' . $id, 'Failure');
    die('Счет не найден в системе');
}

if ($invoice->status != 'Paid') {
    $invoiceId = $invoice->id;

    Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->update([
            'status' => 'Paid',
            'datepaid' => date('Y-m-d H:i:s'),
        ]);

    $transactionDescription = "Payment received via Gateway {$gatewayParams['name']}";
    addTransaction($invoiceId, 0, $amount, 0, 0, $gatewayModuleName);
    addInvoicePayment($invoiceId, $id, $amount, 0, $gatewayParams['paymentmethod']);
    logTransaction($gatewayParams['name'], 'Успешная оплата через' . $gatewayParams['name'] . ' для id ' . $id, 'Success');
    header('HTTP/1.1 200 OK');
    echo json_encode(['code' => 200]);
} else {
    logTransaction($gatewayParams['name'], 'Счет уже оплачен. ID счета ' . $id . '. Сумма платежа:' . $amount . '. Статус платежа: ' . $status, 'Failure');
    die('Счет уже оплачен');
}
?>
