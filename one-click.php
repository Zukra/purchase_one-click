<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Context,
    Bitrix\Currency\CurrencyManager,
    Bitrix\Sale\Order,
    Bitrix\Sale\Basket,
    Bitrix\Sale\Delivery,
    Bitrix\Sale\PaySystem;

global $USER;

Bitrix\Main\Loader::includeModule("sale");
Bitrix\Main\Loader::includeModule("catalog");


$request = Context::getCurrent()->getRequest();
$phone = $request["phone"];
$comment = 'one-click'; // . $request["comment"];
$productId = $request["product_id"];

if (!empty($productId)) {
    $siteId = Context::getCurrent()->getSite();
    $currencyCode = CurrencyManager::getBaseCurrency();

    // Создаём корзину с одним товаром
    $product = [
        'CURRENCY'               => $currencyCode,
        'QUANTITY'               => 1,
        'LID'                    => $siteId,
        'PRODUCT_PROVIDER_CLASS' => '\CCatalogProductProvider',
    ];

    $basket = Basket::create($siteId);
    $item = $basket->createItem('catalog', $productId);
    $item->setFields($product);

    // Создаёт новый заказ
    $order = Order::create($siteId, $USER->isAuthorized() ? $USER->GetID() : CSaleUser::GetAnonymousUserID());
    $order->setPersonTypeId(1);
    $order->setField('CURRENCY', $currencyCode);
    if ($comment) {
        $order->setField('USER_DESCRIPTION', $comment); // Устанавливаем поля комментария покупателя
    }

    $order->setBasket($basket);

// Создаём одну отгрузку и устанавливаем способ доставки - "Без доставки" (он служебный)
    $shipmentCollection = $order->getShipmentCollection();
    $shipment = $shipmentCollection->createItem();
    $service = Delivery\Services\Manager::getById(Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId());
    $shipment->setFields(array(
        'DELIVERY_ID'   => $service['ID'],
        'DELIVERY_NAME' => $service['NAME'],
    ));
    $shipmentItemCollection = $shipment->getShipmentItemCollection();
    $shipmentItem = $shipmentItemCollection->createItem($item);
    $shipmentItem->setQuantity($item->getQuantity());

    // Создаём оплату со способом #2 - CASH
    $paymentCollection = $order->getPaymentCollection();
    $payment = $paymentCollection->createItem();
    $paySystemService = PaySystem\Manager::getObjectById(2);
    $payment->setFields(array(
        'PAY_SYSTEM_ID'   => $paySystemService->getField("PAY_SYSTEM_ID"),
        'PAY_SYSTEM_NAME' => $paySystemService->getField("NAME"),
    ));

    // Устанавливаем свойства
    $propertyCollection = $order->getPropertyCollection();
    $phoneProp = $propertyCollection->getPhone();
    $phoneProp->setValue($phone);
    $nameProp = $propertyCollection->getPayerName();

    // Сохраняем
    $order->doFinalAction(true);
    $result = $order->save();
    if (!$result->isSuccess()) {
        echo json_encode([
            'TYPE'    => "ERROR",
            'MESSAGE' => $result->getErrors()
        ]);
    } else {
        $orderId = $order->getId();
        echo json_encode([
            'TYPE'     => "OK",
            'ORDER_ID' => $orderId,
            'MESSAGE'  => 'Order is added'
        ]);
    }
} else {
    echo json_encode([
        'TYPE'    => "ERROR",
        'MESSAGE' => 'Phone is empty'
    ]);
}