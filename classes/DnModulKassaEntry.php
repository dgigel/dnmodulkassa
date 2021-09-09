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

/**
 * Модель документа.
 *
 * @author Daniel Gigel <daniel@gigel.ru>
 */
class DnModulKassaEntry extends ObjectModel
{
    public $id_entry;
    public $id_order;
    public $doc_id;
    public $doc_type;
    public $payment_type;
    public $print_receipt;
    public $contact;
    public $checkout_datetime;
    public $status;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'dnmodulkassa_entry',
        'primary' => 'id_entry',
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'doc_id' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'doc_type' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'payment_type' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'print_receipt' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'),
            'contact' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'checkout_datetime' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'status' => array('type' => self::TYPE_STRING, 'validate' => 'isString'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate')
        )
    );

    public static function getEntriesByOrderId($order_id)
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'dnmodulkassa_entry` WHERE `id_order`=' . (int)$order_id . ' ORDER BY `id_entry` ASC');
    }

    public static function getEntryByDocId($doc_id)
    {
        $entry_id = Db::getInstance()->getValue('SELECT `id_entry` FROM `' . _DB_PREFIX_ . 'dnmodulkassa_entry` WHERE `doc_id`="' . $doc_id.'"', false);

        if (!$entry_id)
            return false;

        $entry = new DnModulKassaEntry((int)$entry_id);
        return $entry;
    }

}
