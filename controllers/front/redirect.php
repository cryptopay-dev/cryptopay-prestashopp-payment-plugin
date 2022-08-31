<?php

class CryptopayRedirectModuleFrontController extends ModuleFrontController {
    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent() {
        parent::initContent();

        global $link;
        $cart = $this->context->cart;

        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        $total = (float)number_format($cart->getOrderTotal(true, 3), 2, '.', '');
        $currency = Context::getContext()->currency;

        $this->module->validateOrder(
            $cart->id,
            Configuration::get('CRYPTOPAY_MODULE_OS_PENDING'),
            $total,
            $this->module->displayName,
            NULL,
            NULL,
            $currency->id
        );

        $customer = new Customer((int)($cart->id_customer));
        $successurl = $link->getPageLink(
            'order-confirmation',
            true,
            null,
            array(
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $this->module->id,
                'key' => $customer->secure_key
            )
        );
        $unsuccessurl = $link->getModuleLink(
            'cryptopay',
            'cancel',
            array(),
            true
        );

        $params = array(
            'customId' => 'prestashop_order_' . $cart->id,
            'widgetKey' => Configuration::get('CRYPTOPAY_MODULE_OS_WIDGET_KEY'),
            'isShowQr' => Configuration::get('CRYPTOPAY_MODULE_OS_SHOW_QR_CODE') == 'Yes' ? 'true' : 'false',
            'theme' => Configuration::get('CRYPTOPAY_MODULE_OS_THEME'),
            'priceCurrency' => $currency->iso_code,
            'priceAmount' => $total,
            'successRedirectUrl' => $successurl,
            'unsuccessRedirectUrl' => $unsuccessurl,
        );

        $redirectUrl = Configuration::get('CRYPTOPAY_MODULE_OS_ENVIRONMENT') == 'sandbox'
            ? 'https://pay-business-sandbox.cryptopay.me'
            : 'https://business-pay.cryptopay.me';

        $url = $redirectUrl . '?' . http_build_query($params);
        Tools::redirect($url);
    }
}
