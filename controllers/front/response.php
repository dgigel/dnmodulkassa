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

        echo $this->module->displayName;

    }

    protected function disableColumns()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        $this->display_footer = false;
        $this->display_header = false;
    }
}
