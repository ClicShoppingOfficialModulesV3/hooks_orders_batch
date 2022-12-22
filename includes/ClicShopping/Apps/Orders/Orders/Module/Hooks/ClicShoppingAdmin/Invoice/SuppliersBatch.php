<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @licence MIT - Portion of osCommerce 2.4
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Orders\Orders\Module\Hooks\ClicShoppingAdmin\Invoice;

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\CLICSHOPPING;

  use ClicShopping\Apps\Configuration\OrdersStatus\Classes\ClicShoppingAdmin\OrderStatusAdmin;

  use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

  class SuppliersBatch implements \ClicShopping\OM\Modules\HooksInterface
  {
    protected $app;

    public function __construct()
    {

      if (!Registry::exists('Orders')) {
        Registry::set('Orders', new OrdersApp());
      }

      $this->app = Registry::get('Orders');

      $this->label_date = array(array('id' => '0', 'text' => $this->app->getDef('label_text_select')),
        array('id' => '1', 'text' => $this->app->getDef('label_text_24hr')),
        array('id' => '2', 'text' => $this->app->getDef('label_text_48hr')),
        array('id' => '3', 'text' => $this->app->getDef('label_text_72hr')),
        array('id' => '7', 'text' => $this->app->getDef('label_text_week'))
      );
    }

    private function suppliersDropDown()
    {
      $Qsuppliers = $this->app->db->prepare('select distinct suppliers_id,
                                                             suppliers_name
                                            from :table_suppliers
                                            order by suppliers_name
                                           ');
      $Qsuppliers->execute();

      $suppliers[] = ['id' => '0',
        'text' => $this->app->getDef('label_text_select')
      ];

      while ($Qsuppliers->fetch()) {
        $suppliers[] = ['id' => $Qsuppliers->valueInt('suppliers_id'),
          'text' => $Qsuppliers->value('suppliers_name')
        ];
      }

      return $suppliers;
    }

    public function execute()
    {
      if (!defined('CLICSHOPPING_APP_ORDERS_OD_STATUS')) {
        return false;
      }

      $dropdown_suppliers = HTML::selectMenu('dropdown_suppliers', $this->suppliersDropDown());

      $output = '&nbsp;';
      $output .= '
                <style>.modal-dialog {height: 1000px!important;} </style>
                <a data-toggle="modal" data-target="#myModalSuppliers">' . HTML::button($this->app->getDef('suppliers_button'), null, null, 'success') . '</a>
                <div class="modal fade" id="myModalSuppliers" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h4 class="modal-title text-start" id="myModalLabel">' . $this->app->getDef('suppliers_title') . '</h4>
                      </div>
                      <div class="modal-body text-center">
                        ' . HTML::form('suppliers', $this->app->link('SuppliersBatch')) . '
                          <div class="col-md-12">
                            <span class="col-md-6 text-start">' . $this->app->getDef('label_text_date_order') . '</span><span class="col-md-6">' . HTML::selectMenu('supplier_date', $this->label_date) . '</span><br />
                            <span class="col-md-6 text-start">' . $this->app->getDef('entry_status') . '</span><span class="col-md-6">' . OrderStatusAdmin::getDropDownOrderStatus() . '</span><br />
                            <span class="col-md-6 text-start">' . $this->app->getDef('entry_supliers_name') . '</span><span class="col-md-6">' . $dropdown_suppliers . '</span><br />
                          </div>
                          <div class="col-md-12">
                            <span class="col-md-12 text-start">' . $this->app->getDef('label_text_order_number') . '</span>
                            <span class="col-md-6">' . $this->app->getDef('label_text_start') . HTML::inputField('orders_id_start', '', 'placeholder="10"') . '</span>
                            <span class="col-md-6">' . $this->app->getDef('label_text_end') . HTML::inputField('orders_id_end', '', 'placeholder="50"') . '</span><br />
                          </div>
                          <div class="col-md-12 text-start">' . $this->app->getDef('text_note_supplier') . '</div>
                          <div>' . HTML::button($this->app->getDef('label_button_print'), null, null, 'secondary') . '</div>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
                ';

      return $output;
    }
  }