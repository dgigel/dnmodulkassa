<?php
/**
 * МодульКасса: модуль для PrestaShop.
 *
 * @author    Daniel Gigel <daniel@gigel.ru>
 * @author    Maksim T. <zapalm@yandex.com>
 * @copyright 2017 Daniel Gigel
 * @link      https://prestashop.modulez.ru/ru/third-party-data-integration/55-prestashop-and-modulkassa-integration.html Домашняя страница модуля
 * @license   https://ru.bmstu.wiki/MIT_License Лицензия MIT
 */

if (!defined('_PS_VERSION_'))
    exit;

require_once _PS_MODULE_DIR_ . 'dnmodulkassa/autoload.inc.php';

/**
 * Модуль интеграции с сервисом МодульКасса.
 *
 * @author Daniel Gigel <daniel@gigel.ru>
 * @author Maksim T. <zapalm@yandex.com>
 */
class DnModulKassa extends Module
{
    /** Идентификатор модуля (продукта) на домашней странице. */
    const HOMEPAGE_PRODUCT_ID = 55;

    private $conf_default = array(
        'DNMODULKASSA_LOGIN' => '',
        'DNMODULKASSA_PASSWORD' => '',
        'DNMODULKASSA_RETAIL_POINT_ID' => '',
        'DNMODULKASSA_RETAIL_POINT_INFO' => '',
        'DNMODULKASSA_ASSOCIATE_USER' => '',
        'DNMODULKASSA_ASSOCIATE_PASSWORD' => '',
        'DNMODULKASSA_TEST_MODE' => '1',
        'DNMODULKASSA_LOGS_MODE' => '0',
        'DNMODULKASSA_SECRET' => '',
        'DNMODULKASSA_VAT_TAG' => '1105'
    );

    /**
     * @inheritDoc
     *
     * @author Daniel Gigel <daniel@gigel.ru>
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function __construct()
    {
        $this->name          = 'dnmodulkassa';
        $this->tab           = 'billing_invoicing';
        $this->version       = '0.5.0';
        $this->author        = 'Daniel Gigel';
        $this->need_instance = false;
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = 'МодульКасса';
        $this->description = 'PrestaShop <=> МодульКасса';
    }

    /**
     * @inheritDoc
     *
     * @author Daniel Gigel <daniel@gigel.ru>
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function install()
    {
        $result = parent::install();

        if ($result) {
            $result = Db::getInstance()->execute('
                CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'dnmodulkassa_entry (
                    id_entry          INT UNSIGNED            NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    id_order          INT UNSIGNED            NOT NULL,
                    doc_id            VARCHAR(250)            NOT NULL,
                    doc_type          ENUM("SALE", "RETURN")  NOT NULL,
                    payment_type      ENUM("CARD", "CASH")    NOT NULL,
                    print_receipt     TINYINT(1) UNSIGNED     NOT NULL,
                    contact           VARCHAR(250)            NOT NULL,
                    checkout_datetime VARCHAR(100)            NOT NULL,
                    status            VARCHAR(100)            NOT NULL,
                    date_add          DATETIME                NOT NULL,
                    date_upd          DATETIME                NOT NULL
                ) ENGINE = ' . _MYSQL_ENGINE_
            );
        }

        if ($result) {
            foreach ($this->conf_default as $confName => $confValue) {
                if ($confName === 'DNMODULKASSA_SECRET') {
                    $confValue = Tools::passwdGen(32, 'RANDOM');
                }

                Configuration::updateValue($confName, $confValue);
            }

            $result &= $this->registerHook('displayAdminOrder');
            $result &= $this->registerHook('displayBackOfficeHeader');

            $result = (bool)$result;
        }

        if ($result) {
            $tab = \zapalm\prestashopHelpers\helpers\BackendHelper::installTab(
                $this->name,
                'AdminDnModulKassa',
                \zapalm\prestashopHelpers\helpers\BackendHelper::TAB_PARENT_ID_UNLINKED
            );

            $result = \zapalm\prestashopHelpers\helpers\ValidateHelper::isLoadedObject($tab);
        }

        (new \zapalm\prestashopHelpers\components\qualityService\QualityServiceClient(self::HOMEPAGE_PRODUCT_ID))
            ->installModule($this)
        ;

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @author Daniel Gigel <daniel@gigel.ru>
     * @author Maksim T. <zapalm@yandex.com>
     */
    public function uninstall()
    {
        $result = (bool)parent::uninstall();

        if ($result) {
            foreach (array_keys($this->conf_default) as $confName) {
                Configuration::deleteByName($confName);
            }

            $result = Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'dnmodulkassa_entry');
        }

        if ($result) {
            $result = \zapalm\prestashopHelpers\helpers\ModuleHelper::uninstallTabs($this->name);
        }

        (new \zapalm\prestashopHelpers\components\qualityService\QualityServiceClient(self::HOMEPAGE_PRODUCT_ID))
            ->uninstallModule($this)
        ;

        return $result;
    }

    /**
     * @inheritDoc
     *
     * @author Daniel Gigel <daniel@gigel.ru>
     */
    public function hookDisplayAdminOrder($params)
    {
        $configured = (Configuration::get('DNMODULKASSA_SECRET') &&
            Configuration::get('DNMODULKASSA_ASSOCIATE_USER') &&
            Configuration::get('DNMODULKASSA_ASSOCIATE_PASSWORD')) ? true : false;

        $order = new Order((int)$params['id_order']);
        $customer = new Customer($order->id_customer);
        $address = new Address((int)$order->id_address_delivery);
        $entries = DnModulKassaEntry::getEntriesByOrderId($params['id_order']);

        $this->smarty->assign(array(
            'module_settings_link' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&module_name=' . $this->name . '&tab_module=' . $this->tab,
            'configured' => $configured,
            'customer' => $customer,
            'address' => $address,
            'entries' => $entries
        ));
        return $this->display(__FILE__, 'displayAdminOrder.tpl');
    }

    /**
     * @inheritDoc
     *
     * @author Daniel Gigel <daniel@gigel.ru>
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        $this->context->controller->addCSS(($this->_path) . 'views/css/dnmodulkassa.css');
        return '
			<script type="text/javascript">
				var tokenDnModulKassa = "' . Tools::getAdminTokenLite('AdminDnModulKassa') . '";
			</script>
			<script type="text/javascript" src="' . ($this->_path) . 'views/js/dnmodulkassa.js"></script>';
    }

    /**
     * @inheritDoc
     *
     * @author Daniel Gigel <daniel@gigel.ru>
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('settings_submit_save')) {
            $test_mode = (int)Tools::getValue('DNMODULKASSA_TEST_MODE');
            $logs_mode = (int)Tools::getValue('DNMODULKASSA_LOGS_MODE');
            $secret = Tools::getValue('DNMODULKASSA_SECRET');
            $vat_tag = Tools::getValue('DNMODULKASSA_VAT_TAG');
            if (Configuration::updateValue('DNMODULKASSA_TEST_MODE', $test_mode) &&
                Configuration::updateValue('DNMODULKASSA_LOGS_MODE', $logs_mode) &&
                Configuration::updateValue('DNMODULKASSA_SECRET', $secret) &&
                Configuration::updateValue('DNMODULKASSA_VAT_TAG', $vat_tag)
            ) {
                $output .= '
                    <div class="alert alert-success">Настройки модуля сохранены.</div>
                ';
            } else {
                $output .= '
                    <div class="alert alert-danger">Ошибка сохранения настроек модуля.</div>
                ';
            }
        }
        if (Tools::isSubmit('auth_submit_save')) {
            if (Configuration::updateValue('DNMODULKASSA_LOGIN', Tools::getValue('DNMODULKASSA_LOGIN')) &&
                Configuration::updateValue('DNMODULKASSA_RETAIL_POINT_ID', Tools::getValue('DNMODULKASSA_RETAIL_POINT_ID')) &&
                Configuration::updateValue('DNMODULKASSA_PASSWORD', Tools::getValue('DNMODULKASSA_PASSWORD'))
            ) {
                $output .= '
                    <div class="alert alert-success">Данные для авторизации сохранены.</div>
                ';
            } else {
                $output .= '
                    <div class="alert alert-danger">Ошибка сохранения данных авторизации.</div>
                ';
            }
        }

        if (Tools::isSubmit('association_submit_add')) {

            $retailpoint_id = Configuration::get('DNMODULKASSA_RETAIL_POINT_ID');
            $login = Configuration::get('DNMODULKASSA_LOGIN');
            $password = Configuration::get('DNMODULKASSA_PASSWORD');
            $test_mode = Configuration::get('DNMODULKASSA_TEST_MODE');

            if ($retailpoint_id != '' && $login != '' && $password != '') {
                $association_responce = DnModulKassaClient::createAssociation($retailpoint_id, $login, $password);
                if ($association_responce['success']) {
                    $output .= '<div class="alert alert-success">Успешная инициализация интернет-магазина с розничной точкой.</div>';
                } else {
                    $output .= '<div class="alert alert-danger">Ошибка создания связи.</div>';
                }
            } else {
                $output .= '<div class="alert alert-danger">Не заполнены данные для авторизации.</div>';
            }
        }

        if (Tools::isSubmit('association_submit_delete')) {
            DnModulKassaClient::removeCurrentAssociation();
        }

        $output .= '
            <div class="row">
                <div class="col-md-12">
                    <div class="panel">
                        <div class="panel-heading"><img src="' . $this->_path . 'views/img/profile.png" alt="" /> Настройки авторизации</div>
                        <form action="' . $_SERVER['REQUEST_URI'] . '" method="post" class="form-horizontal">
                            <div class="alert alert-info">
                                Войдите в 
                                <a target="_blank" rel="nofollow noopener" href="https://modulkassa.pro/?utm_source=pap&a_aid=0C7FBBDE-E228-4AAE-8B04-67E634BCB03D">
                                    личный кабинет МодульКассы
                                </a>
                                для получения учётных данных.
                                <br>
                                В настройках модуля нужно ввести логин, пароль и ID точки продаж.<br>
                                ID точки продаж - это GUID-идентификатор, который выглядит примерно так: <u>1q2w3e4r-1q2w-1q2w-1q2w3e4r1q2w</u>.<br>
                                Еще нужно ввести токен - это любое кодовое слово, которое придумываете вы сами, например: <u>1q2W3e4R1Q</u>.<br>
                                Остальные настройки модуля установите по вашей необходимости.
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">Логин: </label>
                                <div class="col-lg-9">
                                    <input type="text" class="text form-control" value="' . Configuration::get('DNMODULKASSA_LOGIN') . '" name="DNMODULKASSA_LOGIN">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">Пароль: </label>
                                <div class="col-lg-9">
                                    <input type="password" class="text form-control" value="' . Configuration::get('DNMODULKASSA_PASSWORD') . '" name="DNMODULKASSA_PASSWORD">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">ID точки продаж: </label>
                                <div class="col-lg-9">
                                    <input type="text" class="text form-control" value="' . Configuration::get('DNMODULKASSA_RETAIL_POINT_ID') . '" name="DNMODULKASSA_RETAIL_POINT_ID">
                                </div>
                            </div>
                            <div class="form-group"><input type="submit" name="auth_submit_save" value="Сохранить" class="button btn btn-primary pull-right" /></div>
                        </form>
                    </div>
                </div>
		';

        if (Configuration::get('DNMODULKASSA_LOGIN') != '' &&
            Configuration::get('DNMODULKASSA_RETAIL_POINT_ID') != '' &&
            Configuration::get('DNMODULKASSA_PASSWORD') != ''
        ) {
            $auser = Configuration::get('DNMODULKASSA_ASSOCIATE_USER');
            $apassword = Configuration::get('DNMODULKASSA_ASSOCIATE_PASSWORD');
            $apoint_info = Configuration::get('DNMODULKASSA_RETAIL_POINT_INFO');
            if ($apassword != '' && $auser != '' && $apoint_info != '') {
                $astatus = DnModulKassaClient::getStatus($auser, $apassword);
            }
            $output .= '
                <div class="col-md-12">
                    <div class="panel">
                        <div class="panel-heading"><img src="' . $this->_path . 'views/img/sync.png" alt="" /> Инициализация (связка) интернет-магазина с розничной точкой:</div>
                        <form action="' . $_SERVER['REQUEST_URI'] . '" method="post" class="form-horizontal">
                            <p>Логин & пароль: ' . (($auser != '' && $apassword != '') ? '<b class="text-success">получены</b>' : '<b class="text-danger">не получены</b>') . '</p>
                            <p><b>' . $apoint_info . '</b></p>
                            ' . ((isset($astatus) && $astatus['success']) ? ('<p>Статус: <b>' . $astatus['data']['status'] . '</b> ' . $astatus['data']['dateTime'] . '</p>') : '') . '
                            <div class="form-group">
                            ' . (
                ($auser != '' && $apassword != '') ?
                    '<input type="submit" name="association_submit_delete" value="Удалить" class="button btn btn-danger pull-right" />' :
                    '<input type="submit" name="association_submit_add" value="Связать" class="button btn btn-primary pull-right" />'
                ) . '
                            </div>
                        </form>
                    </div>
                </div>
		    ';
        }

        $output .= '
            </div>
		';

        $output .= '
            <div class="row">
                <div class="col-md-12">
                    <div class="panel">
                        <div class="panel-heading"><img src="' . $this->_path . 'views/img/settings.png" alt="" /> Настройки модуля</div>
                        <form action="' . $_SERVER['REQUEST_URI'] . '" method="post" class="form-horizontal">
                            <div class="form-group">
                                <label class="control-label col-lg-3">Тестовый режим: </label>
                                <div class="col-lg-9">
                                    <span class="switch prestashop-switch fixed-width-lg">
                                        <input type="radio" name="DNMODULKASSA_TEST_MODE" id="DNMODULKASSA_TEST_MODE_on" value="1" ' . ((int)Configuration::get('DNMODULKASSA_TEST_MODE') == 1 ? 'checked="checked"' : '') . '>
                                        <label for="DNMODULKASSA_TEST_MODE_on" class="radioCheck">Вкл</label>
                                        <input type="radio" name="DNMODULKASSA_TEST_MODE" id="DNMODULKASSA_TEST_MODE_off" value="0" ' . ((int)Configuration::get('DNMODULKASSA_TEST_MODE') == 0 ? 'checked="checked"' : '') . '>
                                        <label for="DNMODULKASSA_TEST_MODE_off" class="radioCheck">Выкл</label>
                                        <a class="slide-button btn"></a>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">Вести логи: </label>
                                <div class="col-lg-9">
                                    <span class="switch prestashop-switch fixed-width-lg">
                                        <input type="radio" name="DNMODULKASSA_LOGS_MODE" id="DNMODULKASSA_LOGS_MODE_on" value="1" ' . ((int)Configuration::get('DNMODULKASSA_LOGS_MODE') == 1 ? 'checked="checked"' : '') . '>
                                        <label for="DNMODULKASSA_LOGS_MODE_on" class="radioCheck">Вкл</label>
                                        <input type="radio" name="DNMODULKASSA_LOGS_MODE" id="DNMODULKASSA_LOGS_MODE_off" value="0" ' . ((int)Configuration::get('DNMODULKASSA_LOGS_MODE') == 0 ? 'checked="checked"' : '') . '>
                                        <label for="DNMODULKASSA_LOGS_MODE_off" class="radioCheck">Выкл</label>
                                        <a class="slide-button btn"></a>
                                    </span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">Токен: </label>
                                <div class="col-lg-9">
                                    <input type="text" class="text form-control" value="' . Configuration::get('DNMODULKASSA_SECRET') . '" name="DNMODULKASSA_SECRET">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-lg-3">Тег НДС, согласно ФЗ-54: </label>
                                <div class="col-lg-9">
                                    <select class="form-control" name="DNMODULKASSA_VAT_TAG" id="DNMODULKASSA_VAT_TAG">
                                        <option value="1104" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1104' ? 'selected="selected"' : '') . '>НДС 0%</option>
                                        <option value="1103" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1103' ? 'selected="selected"' : '') . '>НДС 10%</option>
                                        <option value="1102" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1102' ? 'selected="selected"' : '') . '>НДС 20%</option>
                                        <option value="1105" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1105' ? 'selected="selected"' : '') . '>НДС не облагается</option>
                                        <option value="1107" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1107' ? 'selected="selected"' : '') . '>НДС с рассч. ставкой 10%</option>
                                        <option value="1106" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1106' ? 'selected="selected"' : '') . '>НДС с рассч. ставкой 20%</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group"><input type="submit" name="settings_submit_save" value="Сохранить" class="button btn btn-primary pull-right" /></div>
                        </form>
                    </div>
                </div>
            </div>
		';

        $output .= (new \zapalm\prestashopHelpers\widgets\AboutModuleWidget($this))
            ->setProductId(self::HOMEPAGE_PRODUCT_ID)
            ->setLicenseTitle(\zapalm\prestashopHelpers\widgets\AboutModuleWidget::LICENSE_MIT)
            ->setLicenseUrl('https://ru.bmstu.wiki/MIT_License')
        ;

        return $output;
    }
}