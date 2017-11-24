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

class DnModulKassa extends Module
{
    private $conf_default = array(
        'DNMODULKASSA_LOGIN' => '',
        'DNMODULKASSA_PASSWORD' => '',
        'DNMODULKASSA_RETAIL_POINT_ID' => '',
        'DNMODULKASSA_RETAIL_POINT_INFO' => '',
        'DNMODULKASSA_ASSOCIATE_USER' => '',
        'DNMODULKASSA_ASSOCIATE_PASSWORD' => '',
        'DNMODULKASSA_TEST_MODE' => '1'
    );

    public function __construct()
    {
        $this->name = 'dnmodulkassa';
        $this->tab = 'billing_invoicing';
        $this->version = '0.1';
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
        foreach ($this->conf_default as $c => $v)
            Configuration::updateValue($c, $v);

        DnModulKassaHandler::log('Установка модуля.');
        return parent::install();
    }

    public function uninstall()
    {
        foreach ($this->conf_default as $c => $v)
            Configuration::deleteByName($c);

        DnModulKassaHandler::log('Удаление модуля.');
        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '
            <div class="row">
                <div class="col-md-12">
                    <div class="panel">
                        <img src="../modules/dnmodulkassa/logo.png" style="float:left;margin-right: 20px;">
                        <h1>' . $this->displayName . ' <sup>v' . $this->version . '</sup></h1>
                    </div>
                </div>
            </div>
        ';

        if (Tools::isSubmit('testmode_submit_save')) {
            $test_mode = (int)Tools::getValue('DNMODULKASSA_TEST_MODE');
            if (Configuration::updateValue('DNMODULKASSA_TEST_MODE', $test_mode))
                DnModulKassaHandler::log(($test_mode ? 'Включение' : 'Выключение') . ' тестового режима.');
            $output .= '
                <div class="alert alert-success">Тестовый режим ' . ($test_mode ? 'включен' : 'выключен') . '</div>
            ';
        }
        if (Tools::isSubmit('auth_submit_save')) {
            if (Configuration::updateValue('DNMODULKASSA_LOGIN', Tools::getValue('DNMODULKASSA_LOGIN')) &&
                Configuration::updateValue('DNMODULKASSA_RETAIL_POINT_ID', Tools::getValue('DNMODULKASSA_RETAIL_POINT_ID')) &&
                Configuration::updateValue('DNMODULKASSA_PASSWORD', Tools::getValue('DNMODULKASSA_PASSWORD'))
            ) {
                $output .= '
                    <div class="alert alert-success">Данные для авторизации сохранены.</div>
                ';
            }
        }

        if (Tools::isSubmit('association_submit_add')) {

            $retailpoint_id = Configuration::get('DNMODULKASSA_RETAIL_POINT_ID');
            $login = Configuration::get('DNMODULKASSA_LOGIN');
            $password = Configuration::get('DNMODULKASSA_PASSWORD');
            $test_mode = Configuration::get('DNMODULKASSA_TEST_MODE');

            if ($retailpoint_id != '' && $login != '' && $password != '') {
                $association_responce = DnModulKassaHandler::createAssociation($retailpoint_id, $login, $password, $test_mode);
                if ($association_responce['success']) {
                    $output .= '<div class="alert alert-success">Связь магазин-касса настроена.</div>';
                } else {
                    $output .= '<div class="alert alert-danger">Ошибка создания связи.</div>';
                }
            } else {
                $output .= '<div class="alert alert-danger">Не заполнены авторизационные данные.</div>';
            }
        }

        if (Tools::isSubmit('association_submit_delete')) {
            DnModulKassaHandler::removeCurrentAssociation();
        }

        $output .= '
            <div class="row">
                <div class="col-md-6">
                    <div class="panel">
                        <div class="panel-heading"><img src="../img/admin/employee.gif" /> Настройки авторизации:</div>
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
                $astatus = DnModulKassaHandler::getFnStatus($auser, $apassword, Configuration::get('DNMODULKASSA_TEST_MODE'));
            }
            $output .= '
                <div class="col-md-6">
                    <div class="panel">
                        <div class="panel-heading"><img src="../img/admin/cog.gif" /> Инициализация (связка) интернет-магазина с розничной точкой:</div>
                        <form action="' . $_SERVER['REQUEST_URI'] . '" method="post" class="form-horizontal">
                            <p>user: <b>' . $auser . '</b></p>
                            <p>password: <b>' . $apassword . '</b></p>
                            <p><b>' . $apoint_info . '</b></p>
                            '.((isset($astatus)&&$astatus['success']) ? ('<p>Статус: <b>'.$astatus['data']['status'].'</b> '.$astatus['data']['dateTime'].'</p>') : '').'
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
                        <div class="panel-heading"><img src="../img/admin/cog.gif" /> Тестовый режим:</div>
                        <form action="' . $_SERVER['REQUEST_URI'] . '" method="post" class="form-horizontal">
                            <div class="form-group">
                                <label class="control-label col-lg-3">Включен: </label>
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
                            <div class="form-group"><input type="submit" name="testmode_submit_save" value="Сохранить" class="button btn btn-primary pull-right" /></div>
                        </form>
                    </div>
                </div>
            </div>
		';

        return $output;
    }

}
