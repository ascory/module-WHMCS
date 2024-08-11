<?php
function ascory_config() {
    $configarray = array(
        "FriendlyName" => array(
            "Type" => "System",
            "Value" => "Ascory Pay"
        ),
        "key1" => array(
            "FriendlyName" => "Key 1",
            "Type" => "text",
            "Size" => "32",
            "Description" => "Первый ключ"
        ),
        "key2" => array(
            "FriendlyName" => "Key 2",
            "Type" => "text",
            "Size" => "32",
            "Description" => "Второй ключ"
        ),
        "shop" => array(
            "FriendlyName" => "Shop ID",
            "Type" => "text",
            "Size" => "32",
            "Description" => "Айди шопа"
        ),
        "ip" => array(
            "FriendlyName" => "IP",
            "Type" => "text",
            "Size" => "15",
            "Description" => "IP где стоит ваш WHMCS"
        ),
    );
    return $configarray;
}

function ascory_link($params)
{
    $gatewayParams = getGatewayVariables('ascory');

    if (!$gatewayParams['key1'] || !$gatewayParams['key2'] || !$gatewayParams['shop'] || !$gatewayParams['ip']) {
        die('Указаны не все данные в настройках модуля');
    }

    $amount = $params['amount'];
    $shop = $gatewayParams['shop'];
    $id = $params['invoiceid'];
    $key1 = $gatewayParams['key1'];
    $key2 = $gatewayParams['key2'];
    $apiIp = $gatewayParams['ip'];

    $hash = password_hash($key1 . ":" . $key2 . ":" . $apiIp, PASSWORD_BCRYPT);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.ascory.com/v1/item/create");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "shop" => $shop,
        "hash" => $hash,
        "name" => "Инвойс для заказа ID: $id",
        "description" => "Инвойс для WHMCS заказа ID: $id",
        "amount" => $amount
    ]));
    $response = json_decode(curl_exec($ch), true);
    if ($response["code"] !== 200) {
        logActivity("Ascory Pay Error: " . json_encode($response));
        die("Произошла ошибка при создании айтема" . $response);
    }
    $item = $response["data"]["id"];

    curl_setopt($ch, CURLOPT_URL, "https://api.ascory.com/v1/invoice/create");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "shop" => $shop,
        "hash" => $hash,
        "item" => $item,
		"comment" => $id
    ]));
    $response = json_decode(curl_exec($ch), true);
    if ($response["code"] !== 200) {
        logActivity("Ascory Pay Error: " . json_encode($response));
        die("Произошла ошибка при создании инвойса. Подробности: " . $response);
    }

    header("Location: " . $response["data"]["url"]);
    exit;
}
?>
