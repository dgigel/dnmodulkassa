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

class DnModulKassa extends Module
{
    private $conf_default = array(
        'DNMODULKASSA_LOGIN' => '',
        'DNMODULKASSA_PASSWORD' => '',
        'DNMODULKASSA_RETAIL_POINT_ID' => '',
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

        return parent::install();
    }

    public function uninstall()
    {
        foreach ($this->conf_default as $c => $v)
            Configuration::deleteByName($c);

        return parent::uninstall();
    }
}
