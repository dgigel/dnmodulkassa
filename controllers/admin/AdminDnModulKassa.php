<?php

/**
 * @author Daniel Gigel <daniel@gigel.ru>
 * @link http://Daniel.Gigel.ru/
 * Date: 24.11.2017
 * Time: 19:02
 */
class AdminDnModulKassaController extends ModuleAdminController
{
    public function ajaxProcessCreateDoc()
    {
        $errors = array();
        $form_data = Tools::getValue('form_data');
        foreach ($form_data as $k => $v) {
            if (trim($v) == '')
                $errors[] = $k;

        }
        if (count($errors) > 0) {
            die(Tools::jsonEncode(array(
                'success' => false,
                'data' => $errors
            )));
        } else {
            $order = new Order((int)$form_data['id_order']);

            if ($doc = DnModulKassaHandler::createDoc($order, $form_data['doc_type'], $form_data['payment_type'], (int)$form_data['print_receipt'], $form_data['contact'])) {
                $entry = new DnModulKassaEntry();
                $entry->id_order = $order->id;
                $entry->doc_id = $doc['id'];
                $entry->doc_type = $doc['docType'];
                $entry->payment_type = $doc['moneyPositions']['paymentType'];
                $entry->print_receipt = (int)$doc['printReceipt'];
                $entry->contact = $doc['email'];
                $entry->checkout_datetime = $doc['checkoutDateTime'];
                $entry->status = 'ADDED';

                if($sendDoc = DnModulKassaHandler::sendDoc($doc)) {
                    # обрабатываем ответ от сервера
                    # $entry->save();
                    die(Tools::jsonEncode(array(
                        'success' => true,
                        'entry' => $entry,
                        'doc' => $doc
                    )));
                } else {
                    die(Tools::jsonEncode(array(
                        'success' => false,
                        'message' => 'sendDoc error'
                    )));
                }
            } else {
                die(Tools::jsonEncode(array(
                    'success' => false,
                    'message' => 'createDoc error'
                )));
            }
        }
    }

    public function ajaxProcessGetEntries()
    {
        $id_order = (int)Tools::getValue('id_order');
        if (!$id_order)
            die(Tools::jsonEncode(array('success' => false)));

        die(Tools::jsonEncode(array(
            'success' => true,
            'entries' => DnModulKassaEntry::getEntriesByOrderId($id_order)
        )));
    }
}
