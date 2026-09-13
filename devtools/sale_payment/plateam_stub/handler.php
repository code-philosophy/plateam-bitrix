<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Loader;
use Bitrix\Main\Request;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem\ServiceHandler;
use Bitrix\Sale\PaySystem\ServiceResult;

Loc::loadMessages(__FILE__);

class Plateam_stubHandler extends ServiceHandler
{
    private const HANDLER_TAG = 'PLATEAM_STUB';

    public function initiatePay(Payment $payment, ?Request $request = null)
    {
        $sum = (float) $payment->getSum();
        if ($sum <= 0.0001) {
            return $this->buildSuccess($payment, 'zero');
        }

        $this->setExtraParams([
            'SUM' => $sum,
            'CURRENCY' => (string) $payment->getField('CURRENCY'),
            'PAYMENT_ID' => (int) $payment->getId(),
            'PAYMENT_ACCOUNT_NUMBER' => (string) $payment->getField('ACCOUNT_NUMBER'),
            'PAYSYSTEM_ID' => (int) $this->service->getField('ID'),
            'ORDER_ACCOUNT' => (string) $payment->getCollection()->getOrder()->getField('ACCOUNT_NUMBER'),
            'FORM_ACTION' => '/bitrix/tools/sale_ps_result.php',
            'BX_HANDLER' => self::HANDLER_TAG,
        ]);

        return $this->showTemplate($payment, 'template');
    }

    public function processRequest(Payment $payment, Request $request)
    {
        if ($request->get('confirm') === 'Y') {
            return $this->buildSuccess($payment, 'confirm');
        }

        $result = new ServiceResult();
        $result->addError(new \Bitrix\Main\Error('Payment not confirmed'));
        return $result;
    }

    public function sendResponse(ServiceResult $result, Request $request)
    {
        if ($result->isSuccess()) {
            $orderId = 0;
            $paymentId = (int) $this->getPaymentIdFromRequest($request);
            if ($paymentId > 0 && Loader::includeModule('sale')) {
                $row = \Bitrix\Sale\Internals\PaymentTable::getList([
                    'filter' => ['=ID' => $paymentId],
                    'select' => ['ORDER_ID'],
                    'limit' => 1,
                ])->fetch();
                if ($row) {
                    $orderId = (int) $row['ORDER_ID'];
                }
            }
            if ($orderId <= 0) {
                $orderId = (int) $request->get('ORDER_ID');
            }
            if ($orderId > 0 && isset($_SESSION) && is_array($_SESSION)) {
                $_SESSION['PLATEAM_AWAIT_FINISH_ORDER'] = $orderId;
            }

            // /personal/order/ often redirects to /personal/ and drops query params.
            // Land on the order page that keeps ORDER_ID + plateam_finish for the widget poll.
            if ($orderId > 0) {
                LocalRedirect('/personal/order/make/?ORDER_ID=' . $orderId . '&plateam_finish=1');
            }

            $path = (string) \Bitrix\Main\Config\Option::get('sale', 'sale_ps_success_path', '/personal/order/');
            if ($path === '' || $path === '/') {
                $path = '/personal/order/';
            }
            $sep = (strpos($path, '?') !== false) ? '&' : '?';
            LocalRedirect($path . $sep . 'plateam_finish=1');
        }

        LocalRedirect('/personal/order/make/?plateam_pay_error=Y');
    }

    public function getCurrencyList()
    {
        return ['RUB'];
    }

    public static function getIndicativeFields()
    {
        return ['BX_HANDLER' => self::HANDLER_TAG];
    }

    public function getPaymentIdFromRequest(Request $request)
    {
        $paymentId = (int) $request->get('PAYMENT_ID');
        if ($paymentId > 0) {
            return $paymentId;
        }

        $account = trim((string) $request->get('PAYMENT_ACCOUNT_NUMBER'));
        if ($account === '' || !Loader::includeModule('sale')) {
            return 0;
        }

        $row = \Bitrix\Sale\Internals\PaymentTable::getList([
            'filter' => ['=ACCOUNT_NUMBER' => $account],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        return $row ? (int) $row['ID'] : 0;
    }

    private function buildSuccess(Payment $payment, string $mode): ServiceResult
    {
        $result = new ServiceResult();
        $result->setOperationType(ServiceResult::MONEY_COMING);
        $result->setPsData([
            'PS_STATUS' => 'Y',
            'PS_STATUS_CODE' => '200',
            'PS_STATUS_DESCRIPTION' => 'PLATEAM stub (' . $mode . ')',
            'PS_SUM' => $payment->getSum(),
            'PS_CURRENCY' => $payment->getField('CURRENCY'),
            'PS_RESPONSE_DATE' => new \Bitrix\Main\Type\DateTime(),
        ]);
        return $result;
    }
}
