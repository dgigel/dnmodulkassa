<?php
/**
 * DnModulKassa PrestaShop module main file.
 * @author Daniel Gigel <daniel@gigel.ru>
 * @link http://Daniel.Gigel.ru/
 * Date: 23.11.2017
 * Time: 12:19
 */

if (!defined('_PS_VERSION_'))
    exit;

@require_once _PS_MODULE_DIR_ . 'dnmodulkassa/dnmodulkassa_handler.php';
include_once _PS_MODULE_DIR_ . 'dnmodulkassa/classes/DnModulKassaEntry.php';

class DnModulKassa extends Module
{
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

    public function __construct()
    {
        $this->name = 'dnmodulkassa';
        $this->tab = 'billing_invoicing';
        $this->version = '0.1.3';
        $this->author = 'Daniel.Gigel.ru';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.1.12');
        $this->secure_key = Tools::encrypt($this->name);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'МодульКасса';
        $this->description = 'PrestaShop <=> МодульКасса';

        $this->conf = Configuration::getMultiple(array_keys($this->conf_default));
    }

    public function install()
    {
        foreach ($this->conf_default as $c => $v) {
            if ($c == 'DNMODULKASSA_SECRET')
                $v = Tools::passwdGen(32, 'RANDOM');
            Configuration::updateValue($c, $v);
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dnmodulkassa_entry` (
			`id_entry` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`id_order` INT UNSIGNED NOT NULL,
			`doc_id` VARCHAR(250) NOT NULL,
            `doc_type` ENUM("SALE", "RETURN") NOT NULL,
			`payment_type` ENUM("CARD", "CASH") NOT NULL,
			`print_receipt` TINYINT(1) UNSIGNED NOT NULL,
			`contact` VARCHAR(250) NOT NULL,
			`checkout_datetime` VARCHAR(100) NOT NULL,
			`status` VARCHAR(100) NOT NULL,
			`date_add` DATETIME NOT NULL,
	        `date_upd` DATETIME NOT NULL
			) ENGINE=' . _MYSQL_ENGINE_;

        if (!Db::getInstance()->execute($sql))
            return false;

        if (!parent::install())
            return false;

        if (!$this->installModuleTab('AdminDnModulKassa', 'МодульКасса', -1))
            return false;

        return $this->registerHook('displayAdminOrder')
            && $this->registerHook('BackOfficeHeader');
    }

    public function uninstall()
    {
        foreach ($this->conf_default as $c => $v)
            Configuration::deleteByName($c);

        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'dnmodulkassa_entry`';
        if (!Db::getInstance()->execute($sql))
            return false;

        if (!$this->uninstallModuleTab('AdminDnModulKassa'))
            return false;

        return parent::uninstall();
    }

    public function hookdisplayAdminOrder($params)
    {
        $configured = (Configuration::get('DNMODULKASSA_SECRET') &&
            Configuration::get('DNMODULKASSA_ASSOCIATE_USER') &&
            Configuration::get('DNMODULKASSA_ASSOCIATE_PASSWORD')) ? true : false;

        $order = new Order((int)$params['id_order']);
        $customer = new Customer($order->id_customer);
        $entries = DnModulKassaEntry::getEntriesByOrderId($params['id_order']);

        $this->smarty->assign(array(
            'module_settings_link' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->name . '&module_name=' . $this->name . '&tab_module=' . $this->tab,
            'configured' => $configured,
            'customer' => $customer,
            'entries' => $entries
        ));
        return $this->display(__FILE__, 'displayAdminOrder.tpl');
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->context->controller->addCSS(($this->_path) . 'views/css/dnmodulkassa.css');
        return '
			<script type="text/javascript">
				var tokenDnModulKassa = "' . Tools::getAdminTokenLite('AdminDnModulKassa') . '";
			</script>
			<script type="text/javascript" src="' . ($this->_path) . 'views/js/dnmodulkassa.js"></script>';
    }

    public function getContent()
    {
        $output = '
            <div class="row">
                <div class="col-md-12">
                    <div class="panel dnmodulkassa-header">
                        <img class="dnmodulkassa-logo" src="' . $this->_path . 'logo.png">
                        <h1>' . $this->displayName . ' <sup>v' . $this->version . '</sup></h1>
                    </div>
                </div>
            </div>
        ';

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
                $association_responce = DnModulKassaHandler::createAssociation($retailpoint_id, $login, $password);
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
            DnModulKassaHandler::removeCurrentAssociation();
        }

        $output .= '
            <div class="row">
                <div class="col-md-6">
                    <div class="panel">
                        <div class="panel-heading"><img src="' . $this->_path . 'views/img/profile.png" /> Настройки авторизации:</div>
                        <form action="' . $_SERVER['REQUEST_URI'] . '" method="post" class="form-horizontal">
                            <p>Учетные данные <a target="_blank" href="https://service.modulpos.ru/">МодульКассы</a></p>
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
                $astatus = DnModulKassaHandler::getStatus($auser, $apassword);
            }
            $output .= '
                <div class="col-md-6">
                    <div class="panel">
                        <div class="panel-heading"><img src="' . $this->_path . 'views/img/sync.png" /> Инициализация (связка) интернет-магазина с розничной точкой:</div>
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
                <div class="col-md-6">
                    <div class="panel">
                        <div class="panel-heading"><img src="' . $this->_path . 'views/img/settings.png" /> Настройки модуля:</div>
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
                                        <option value="1102" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1102' ? 'selected="selected"' : '') . '>НДС 18%</option>
                                        <option value="1105" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1105' ? 'selected="selected"' : '') . '>НДС не облагается</option>
                                        <option value="1107" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1107' ? 'selected="selected"' : '') . '>НДС с рассч. ставкой 10/110</option>
                                        <option value="1106" ' . (Configuration::get('DNMODULKASSA_VAT_TAG') == '1106' ? 'selected="selected"' : '') . '>НДС с рассч. ставкой 18/118</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group"><input type="submit" name="settings_submit_save" value="Сохранить" class="button btn btn-primary pull-right" /></div>
                        </form>
                    </div>
                </div>
            </div>
		';

        return $output;
    }

    private function installModuleTab($tab_class, $tab_name, $id_tab_parent)
    {
        $tab = new Tab();
        $tab->class_name = $tab_class;
        $tab->module = $this->name;
        $tab->id_parent = $id_tab_parent;

        $languages = Language::getLanguages();
        foreach ($languages as $lang)
            $tab->name[$lang['id_lang']] = $this->l($tab_name);

        return $tab->save();
    }

    private function uninstallModuleTab($tab_class)
    {
        $idTab = Tab::getIdFromClassName($tab_class);
        if ($idTab != 0) {
            $tab = new Tab($idTab);
            $tab->delete();
            return true;
        }
        return false;
    }

}
