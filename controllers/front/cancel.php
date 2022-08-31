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

class CryptopayCancelModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * @see FrontController::initContent()
     */
    public function initContent() {
        parent::initContent();

        global $link;

        $cart_id = str_replace(
            'prestashop_order_', "", 'prestashop_order_' . Tools::getValue('customId')
        );
        $order_id = (int) Order::getIdByCartId((int)$cart_id);
        $history           = new OrderHistory();
        $history->id_order = $order_id;
        $history->changeIdOrderState((int)Configuration::get('PS_OS_CANCELED'), $order_id);
        $history->save();

        Tools::redirect(
            $link->getPageLink('order-detail', true, null, 'id_order=' . $order_id)
        );
    }
}
