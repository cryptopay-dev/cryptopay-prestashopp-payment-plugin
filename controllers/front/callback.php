<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CryptopayCallbackModuleFrontController extends ModuleFrontController
{
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;
    public $ssl = true;

    /**
     * @see FrontController::postProcess()
     */
    public function postProcess() {
        try {
            $request = file_get_contents('php://input');

            if (!$this->validateCallback($request, $_SERVER['HTTP_X_CRYPTOPAY_SIGNATURE'])) {
                return 'Webhook validation failed.';
            }

            $body = json_decode($request, true);

            if ($body['type'] != 'Invoice') {
                return 'It is not Invoice';
            }

            $data = $body['data'];
            $cart_id = str_replace(
                'prestashop_order_', "", 'prestashop_order_' . $data['custom_id']
            );
            $order_id = (int) Order::getIdByCartId((int)$cart_id);

            $history           = new OrderHistory();
            $history->id_order = $order_id;

            if ($data['status'] == 'new') {
                $history->changeIdOrderState((int)Configuration::get('CRYPTOPAY_MODULE_OS_PENDING'), $order_id);
                exit('*new*');
            }

            if ($data['status'] == 'completed' || $data['status'] == 'unresolved' && $data['status_context'] == 'overpaid') {
                $history->changeIdOrderState((int)Configuration::get('CRYPTOPAY_MODULE_OS_COMPLETE'), $order_id);
                exit('*completed*');
            }

            if ($data['status'] == 'cancelled' || $data['status'] == 'refunded' || $data['status'] == 'unresolved') {
                $history->changeIdOrderState((int)Configuration::get('CRYPTOPAY_MODULE_OS_REFUSE'), $order_id);
                exit('*cancelled*');
            }
        } catch (Exception $e) {
            exit(get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * Validate the callback request
     */
    private function validateCallback($body, $signature)
    {
        $callbackSecret = Configuration::get('CRYPTOPAY_MODULE_OS_CALLBACK_SECRET');
        $expected = hash_hmac('sha256', $body, $callbackSecret);
        return $expected === $signature;
    }
}
