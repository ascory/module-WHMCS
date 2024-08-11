<?php
function ascory_config() {
    $configarray = array(
        "FriendlyName" => array(
            "Type" => "System",
            "Value" => "Ascory Pay"
        ),
        "hash" => array(
            "FriendlyName" => "Hash",
            "Type" => "text",
            "Size" => "32",
            "Description" => "Хэш"
        ),
        "shop" => array(
            "FriendlyName" => "Shop ID",
            "Type" => "text",
            "Size" => "32",
            "Description" => "Айди шопа"
        )
    );
    return $configarray;
}

function ascory_link($params)
{
    $gatewayParams = getGatewayVariables('ascory');

    if (!$gatewayParams['hash'] || !$gatewayParams['shop']) {
        die('Указаны не все данные в настройках модуля');
    }

    $amount = $params['amount'];
    $shop = $gatewayParams['shop'];
    $id = $params['invoiceid'];
    $hash = $gatewayParams['hash'];

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
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        logActivity("Ascory Pay cURL Error: " . curl_error($ch));
        die("Произошла ошибка при выполнении запроса: " . curl_error($ch));
    }
    
    $response = json_decode($response, true);
    if ($response["code"] !== 200) {
        logActivity("Ascory Pay Error: " . json_encode($response));
        die("Произошла ошибка при создании айтема: " . json_encode($response));
    }
    $item = $response["data"]["id"];

    curl_setopt($ch, CURLOPT_URL, "https://api.ascory.com/v1/invoice/create");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "shop" => $shop,
        "hash" => $hash,
        "item" => $item,
        "comment" => $id
    ]));
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        logActivity("Ascory Pay cURL Error: " . curl_error($ch));
        die("Произошла ошибка при выполнении запроса: " . curl_error($ch));
    }
    
    $response = json_decode($response, true);
    if ($response["code"] !== 200) {
        logActivity("Ascory Pay Error: " . json_encode($response));
        die("Произошла ошибка при создании инвойса. Подробности: " . json_encode($response));
    }

    header("Location: " . $response["data"]["url"]);
    exit;
}
?>
