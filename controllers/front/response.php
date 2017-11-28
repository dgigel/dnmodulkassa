<?php

/**
 * @author Daniel Gigel <daniel@gigel.ru>
 * @link http://Daniel.Gigel.ru/
 * Date: 24.11.2017
 * Time: 19:02
 */
class DnModulKassaResponseModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->disableColumns();

        $token = Tools::getValue('token');
        $doc_id = Tools::getValue('doc_id');

        if (!$token OR !$doc_id) {
            header('HTTP/1.0 400 Bad Request');
            die('<h1>400 Bad Request</h1>');
        }

        $token = trim($token);
        $doc_id = trim($doc_id);

        $validate = DnModulKassaHandler::validateToken($token, $doc_id);

        if (!$validate) {
            header('HTTP/1.0 403 Forbidden');
            die('<h1>403 Forbidden</h1>');
        }

        $entry = DnModulKassaEntry::getEntryByDocId($doc_id);
        if (!$entry OR !is_object($entry)) {
            header('HTTP/1.0 404 Not Found');
            die('<h1>404 Not Found</h1>');
        }

        $status = DnModulKassaHandler::getDocStatus($doc_id);

        if ($status['data'] && $status['data']['status']) {
            $entry->status = $status['data']['status'];
            $entry->save();
        }

        header('Content-Type: application/json');
        die(Tools::jsonEncode(array()));
    }

    protected function disableColumns()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        $this->display_footer = false;
        $this->display_header = false;
    }
}
