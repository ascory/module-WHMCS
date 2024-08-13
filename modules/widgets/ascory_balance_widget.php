<?php

add_hook('AdminHomeWidgets', 1, function() {
    return new AscoryBalanceWidget();
});

/**
 * Ascory Balance Widget.
 */
class AscoryBalanceWidget extends \WHMCS\Module\AbstractWidget
{
    protected $title = 'Ascory Balance';
    protected $description = 'Показывает баланс шопа Ascory.';
    protected $weight = 150;
    protected $columns = 1;
    protected $cache = true;
    protected $cacheExpiry = 120;
    protected $requiredPermission = '';

    public function getData()
    {
        $gatewayParams = getGatewayVariables('ascory');
        
        if (!$gatewayParams['hash'] || !$gatewayParams['shop']) {
            return [
                'amount' => 'Ошибка: Настройки модуля Ascory не настроены должным образом.',
                'hold' => 'Ошибка: Настройки модуля Ascory не настроены должным образом.'
            ];
        }

        $url = 'https://api.ascory.com/v1/shop/balance';
        $postData = json_encode([
            'shop' => $gatewayParams['shop'],
            'hash' => $gatewayParams['hash']
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        $amount = isset($responseData['data']['amount']) ? $responseData['data']['amount'] : 'Неизвестно';
        $hold = isset($responseData['data']['hold']) ? $responseData['data']['hold'] : 'Неизвестно';
        
        return [
            'amount' => $amount,
            'hold' => $hold
        ];
    }

    public function generateOutput($data)
    {
        $amount = htmlspecialchars($data['amount']);
        $hold = htmlspecialchars($data['hold']);

        return <<<EOF
<div class="widget-content-padded">
    <div style="background-color: #d4edda; padding: 10px; border-radius: 5px; color: #155724; border: 1px solid #c3e6cb;">
        Доступные средства: $amount
    </div>
    <div style="background-color: #fff3cd; padding: 10px; border-radius: 5px; color: #856404; border: 1px solid #ffeeba; margin-top: 10px;">
        Средства в холде: $hold
    </div>
</div>
EOF;
    }
}
