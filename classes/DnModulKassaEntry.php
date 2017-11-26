<?php

/**
 * @author Daniel Gigel <daniel@gigel.ru>
 * @link http://Daniel.Gigel.ru/
 * Date: 24.11.2017
 * Time: 21:02
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
        return Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'dnmodulkassa_entry WHERE id_order=' . (int)$order_id);
    }

}