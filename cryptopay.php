<?php

if (!defined('_PS_VERSION_'))
    exit;

class Cryptopay extends PaymentModule
{
    protected $_html = '';

    private $environment = 'sandbox';
    private $showQrCode = '0';
    private $theme = 'light';
    private $widgetKey = null;
    private $callbackUrl = null;
    private $callbackSecret = null;

    public function __construct()
    {
        $this->name = 'cryptopay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Cryptopay';
        $this->controllers = array('redirect', 'callback');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $config = Configuration::getMultiple(array(
            'CRYPTOPAY_MODULE_OS_ENVIRONMENT',
            'CRYPTOPAY_MODULE_OS_WIDGET_KEY',
            'CRYPTOPAY_MODULE_OS_CALLBACK_URL',
            'CRYPTOPAY_MODULE_OS_CALLBACK_SECRET',
            'CRYPTOPAY_MODULE_OS_BUTTON_TITLE',
            'CRYPTOPAY_MODULE_OS_SHOW_QR_CODE',
            'CRYPTOPAY_MODULE_OS_THEME',
            'CRYPTOPAY_MODULE_OS_PENDING',
            'CRYPTOPAY_MODULE_OS_COMPLETE',
            'CRYPTOPAY_MODULE_OS_REFUSE'
        ));

        if (!empty($config['CRYPTOPAY_MODULE_OS_WIDGET_KEY']))
            $this->widgetKey = $config['CRYPTOPAY_MODULE_OS_WIDGET_KEY'];
        if (!empty($config['CRYPTOPAY_MODULE_OS_CALLBACK_SECRET']))
            $this->callbackSecret = $config['CRYPTOPAY_MODULE_OS_CALLBACK_SECRET'];

        if (empty($config['CRYPTOPAY_MODULE_OS_ENVIRONMENT']))
            Configuration::updateValue('CRYPTOPAY_MODULE_OS_ENVIRONMENT', $this -> environment);
        else
            $this->environment = $config['CRYPTOPAY_MODULE_OS_ENVIRONMENT'];

        if (empty($config['CRYPTOPAY_MODULE_OS_SHOW_QR_CODE']))
            Configuration::updateValue('CRYPTOPAY_MODULE_OS_SHOW_QR_CODE', $this -> showQrCode);
        else
            $this->showQrCode = $config['CRYPTOPAY_MODULE_OS_SHOW_QR_CODE'];

        if (empty($config['CRYPTOPAY_MODULE_OS_THEME']))
            Configuration::updateValue('CRYPTOPAY_MODULE_OS_THEME', $this -> theme);
        else
            $this->theme = $config['CRYPTOPAY_MODULE_OS_THEME'];

        if (empty($config['CRYPTOPAY_MODULE_OS_BUTTON_TITLE']))
            Configuration::updateValue('CRYPTOPAY_MODULE_OS_BUTTON_TITLE', 'Cryptopay');
        if (empty($config['CRYPTOPAY_MODULE_OS_PENDING']))
            Configuration::updateValue('CRYPTOPAY_MODULE_OS_PENDING', 3);
        if (empty($config['CRYPTOPAY_MODULE_OS_COMPLETE']))
            Configuration::updateValue('CRYPTOPAY_MODULE_OS_COMPLETE', 25);
        if (empty($config['CRYPTOPAY_MODULE_OS_REFUSE']))
            Configuration::updateValue('CRYPTOPAY_MODULE_OS_REFUSE', 8);

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Cryptopay');
        $this->description = $this->l('Cryptocurrency payment gateway');
        $this->confirmUninstall = $this->l(
            'This will remove Cryptopay Shopping Cart module from your system! Do you really wish to proceed?'
        );

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }

        $message = $this->isValid();
        if ($message !== true) {
            $this->warning = $this->l($message);
        }
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('paymentOptions');
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('CRYPTOPAY_MODULE_OS_ENVIRONMENT')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_WIDGET_KEY')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_CALLBACK_URL')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_CALLBACK_SECRET')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_BUTTON_TITLE')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_SHOW_QR_CODE')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_THEME')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_PENDING')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_COMPLETE')
            || !Configuration::deleteByName('CRYPTOPAY_MODULE_OS_REFUSE')
            || !parent::uninstall())
            return false;
        return $this->unregisterHook('paymentOptions') && parent::uninstall();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
            $this->getExternalPaymentOption()
        ];
        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getExternalPaymentOption()
    {
        $externalOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $externalOption->setCallToActionText(Configuration::get('CRYPTOPAY_MODULE_OS_BUTTON_TITLE'))
            ->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    'redirect',
                    array(),
                    true
                )
            );

        return $externalOption;
    }

    protected function generateForm()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = sprintf("%02d", $i);
        }

        $years = [];
        for ($i = 0; $i <= 10; $i++) {
            $years[] = date('Y', strtotime('+' . $i . ' years'));
        }

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
            'months' => $months,
            'years' => $years,
        ]);

        return $this->context->smarty->fetch('module:paymentexample/views/templates/front/payment_form.tpl');
    }

    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('moduleCryptopaySubmit')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        return ($output . $this->displayForm());
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_ENVIRONMENT', Tools::getValue('CRYPTOPAY_MODULE_OS_ENVIRONMENT'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_WIDGET_KEY', Tools::getValue('CRYPTOPAY_MODULE_OS_WIDGET_KEY'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_CALLBACK_URL', Tools::getValue('CRYPTOPAY_MODULE_OS_CALLBACK_URL'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_CALLBACK_SECRET', Tools::getValue('CRYPTOPAY_MODULE_OS_CALLBACK_SECRET'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_BUTTON_TITLE', Tools::getValue('CRYPTOPAY_MODULE_OS_BUTTON_TITLE'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_SHOW_QR_CODE', Tools::getValue('CRYPTOPAY_MODULE_OS_SHOW_QR_CODE'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_THEME', Tools::getValue('CRYPTOPAY_MODULE_OS_THEME'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_PENDING', Tools::getValue('CRYPTOPAY_MODULE_OS_PENDING'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_COMPLETE', Tools::getValue('CRYPTOPAY_MODULE_OS_COMPLETE'));
        Configuration::updateValue('CRYPTOPAY_MODULE_OS_REFUSE', Tools::getValue('CRYPTOPAY_MODULE_OS_REFUSE'));
    }

    private function displayForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'moduleCryptopaySubmit';
        $helper->toolbar_btn = array(
            'back' => array(
                'href' => AdminController::$currentIndex . '&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back')
            )
        );
        $helper->fields_value = $this->readConfiguration();
        return $helper->generateForm($this->getFormArray());
    }

    private function getFormArray()
    {
        $generic_inputs = array(
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'select',
                'name' => 'CRYPTOPAY_MODULE_OS_ENVIRONMENT',
                'label' => $this->l('Environment'),
                'hint' => 'You can use Sandbox environment for testing',
                'options' => array(
                    'query' => array(
                        array(
                            'id_cryptopay_module_mode' => 'sandbox',
                            'name' => $this->l('Sandbox')
                        ),
                        array(
                            'id_cryptopay_module_mode' => 'production',
                            'name' => $this->l('Production')
                        )
                    ),
                    'id' => 'id_cryptopay_module_mode',
                    'name' => 'name'
                )
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'text',
                'name' => 'CRYPTOPAY_MODULE_OS_WIDGET_KEY',
                'label' => $this->l('Widget key'),
                'desc' => 'You can get it from the <a href="https://business.cryptopay.me/app/settings/widget" target="_blank">Cryptopay</a> service Settings -> Widget',
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'text',
                'name' => 'CRYPTOPAY_MODULE_OS_CALLBACK_URL',
                'disabled' => true,
                'label' => $this->l('Callback url'),
                'desc' => '1. <a href="https://business.cryptopay.me" target="_blank">Log in</a> to your account on business.cryptopay.me'
                    . '<br/>'
                    . '2. Then go to <a href="https://business.cryptopay.me/app/settings/api" target="_blank"> the Settings -&gt; API page </a> and save '
                    . $this->context->link->getModuleLink('cryptopay', 'callback', array('action' => 'progress'))
                    . ' in the Callback URL field'
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'text',
                'name' => 'CRYPTOPAY_MODULE_OS_CALLBACK_SECRET',
                'label' => $this->l('Callback secret'),
                'desc' => 'Get the Callback secret via the Settings -> API page in your account on <a href="https://business.cryptopay.me/app/settings/widget" target="_blank">business.cryptopay.me</a>',
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'text',
                'name' => 'CRYPTOPAY_MODULE_OS_BUTTON_TITLE',
                'label' => $this->l('Title'),
                'hint' => 'This controls the title which the user sees during checkout'
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'select',
                'name' => 'CRYPTOPAY_MODULE_OS_SHOW_QR_CODE',
                'label' => $this->l('Show QR code'),
                'options' => array(
                    'query' => array(
                        array(
                            'id_cryptopay_module_qr' => '0',
                            'name' => $this->l('No')
                        ),
                        array(
                            'id_cryptopay_module_qr' => '1',
                            'name' => $this->l('Yes')
                        )
                    ),
                    'id' => 'id_cryptopay_module_qr',
                    'name' => 'name'
                ),
                'hint' => 'Select \'Yes\' to open the QR code on the page'
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'select',
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'CRYPTOPAY_MODULE_OS_THEME',
                'label' => $this->l('Theme'),
                'options' => array(
                    'query' => array(
                        array(
                            'id_cryptopay_module_theme' => 'dark',
                            'name' => $this->l('Dark')
                        ),
                        array(
                            'id_cryptopay_module_theme' => 'light',
                            'name' => $this->l('Light')
                        )
                    ),
                    'id' => 'id_cryptopay_module_theme',
                    'name' => 'name'
                ),
                'hint' => 'To control the color design of the payment page'
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'select',
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'CRYPTOPAY_MODULE_OS_PENDING',
                'label' => $this->l('Order status for pending payments'),
                'options' => array(
                    'query' => OrderState::getOrderStates((int)Configuration::get('PS_LANG_DEFAULT')),
                    'id' => 'id_order_state',
                    'name' => 'name'
                ),
                'hint' => 'Order status after redirect to payment page'
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'select',
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'CRYPTOPAY_MODULE_OS_COMPLETE',
                'label' => $this->l('Order status for successful payments'),
                'options' => array(
                    'query' => OrderState::getOrderStates((int)Configuration::get('PS_LANG_DEFAULT')),
                    'id' => 'id_order_state',
                    'name' => 'name'
                ),
                'hint' => 'Order status after successful payment'
            ),
            array(
                'col' => 3,
                'tab' => 'general',
                'type' => 'select',
                'prefix' => '<i class="icon icon-tag"></i>',
                'name' => 'CRYPTOPAY_MODULE_OS_REFUSE',
                'label' => $this->l('Order status for refused payments'),
                'options' => array(
                    'query' => array_merge(
                        array(
                            array(
                                'id_order_state' => 0,
                                'id_lang' => (int)Configuration::get('PS_LANG_DEFAULT'),
                                'name' => '')
                        ),
                        OrderState::getOrderStates((int)Configuration::get('PS_LANG_DEFAULT'))
                    ),
                    'id' => 'id_order_state',
                    'name' => 'name'
                ),
                'hint' => 'Order status after unsuccessful payment'
            )
        );

        $form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration'),
                    'icon' => 'icon-cogs'
                ),
                'tabs' => array(
                    'general' => $this->l("General"),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right'
                ),
            )
        );

        foreach ($generic_inputs as $v) {
            $form['form']['input'][] = $v;
        }

        return array($form);
    }

    private function isValid()
    {
        if (!function_exists('curl_version')) {
            return 'CURL PHP extension must be installed and enabled before using this module.';
        } elseif (($this->widgetKey == '') or ($this->callbackSecret == '') or ($this->callbackUrl == '')) {
            return 'Your Cryptopay Shopping Cart details must be configured before using this module.';
        }
        return true;
    }

    private function readConfiguration()
    {
        return array(
            'CRYPTOPAY_MODULE_OS_ENVIRONMENT' => Tools::getValue('CRYPTOPAY_MODULE_OS_ENVIRONMENT', Configuration::get('CRYPTOPAY_MODULE_OS_ENVIRONMENT')),
            'CRYPTOPAY_MODULE_OS_WIDGET_KEY' => Tools::getValue('CRYPTOPAY_MODULE_OS_WIDGET_KEY', Configuration::get('CRYPTOPAY_MODULE_OS_WIDGET_KEY')),
            'CRYPTOPAY_MODULE_OS_CALLBACK_URL' => $this->context->link->getModuleLink('cryptopay', 'callback', array('action' => 'progress')),
            'CRYPTOPAY_MODULE_OS_CALLBACK_SECRET' => Tools::getValue('CRYPTOPAY_MODULE_OS_CALLBACK_SECRET', Configuration::get('CRYPTOPAY_MODULE_OS_CALLBACK_SECRET')),
            'CRYPTOPAY_MODULE_OS_BUTTON_TITLE' => Tools::getValue('CRYPTOPAY_MODULE_OS_BUTTON_TITLE', Configuration::get('CRYPTOPAY_MODULE_OS_BUTTON_TITLE')),
            'CRYPTOPAY_MODULE_OS_SHOW_QR_CODE' => Tools::getValue('CRYPTOPAY_MODULE_OS_SHOW_QR_CODE', Configuration::get('CRYPTOPAY_MODULE_OS_SHOW_QR_CODE')),
            'CRYPTOPAY_MODULE_OS_THEME' => Tools::getValue('CRYPTOPAY_MODULE_OS_THEME', Configuration::get('CRYPTOPAY_MODULE_OS_THEME')),
            'CRYPTOPAY_MODULE_OS_PENDING' => Tools::getValue('CRYPTOPAY_MODULE_OS_PENDING', Configuration::get('CRYPTOPAY_MODULE_OS_PENDING')),
            'CRYPTOPAY_MODULE_OS_COMPLETE' => Tools::getValue('CRYPTOPAY_MODULE_OS_COMPLETE', Configuration::get('CRYPTOPAY_MODULE_OS_COMPLETE')),
            'CRYPTOPAY_MODULE_OS_REFUSE' => Tools::getValue('CRYPTOPAY_MODULE_OS_REFUSE', Configuration::get('CRYPTOPAY_MODULE_OS_REFUSE')),
        );
    }
}
