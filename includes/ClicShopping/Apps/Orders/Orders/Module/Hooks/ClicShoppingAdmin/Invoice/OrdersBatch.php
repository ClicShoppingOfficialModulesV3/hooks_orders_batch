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

  class OrdersBatch implements \ClicShopping\OM\Modules\HooksInterface
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

    public function execute()
    {
      if (!defined('CLICSHOPPING_APP_ORDERS_OD_STATUS')) {
        return false;
      }

      $output = '&nbsp;';
      $output .= '
              <a data-toggle="modal" data-target="#myModalBatchOrder">' . HTML::button($this->app->getDef('button_orders_batch'), null, null, 'primary') . '</a>
              <div class="modal fade" id="myModalBatchOrder" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h4 class="modal-title text-start" id="myModalLabel">' . $this->app->getDef('orders_batch_heading') . '</h4>
                    </div>
                    <div class="modal-body text-center">
                      ' . HTML::form('invoice_batch', $this->app->link('InvoiceBatch')) . '
                        <div class="col-md-12">
                        <span class="col-md-6 text-start">' . $this->app->getDef('label_text_date_order') . '</span><span class="col-md-6">' . HTML::selectMenu('orders_date', $this->label_date) . '</span><br />
                        <span class="col-md-6 text-start">' . $this->app->getDef('entry_status') . '</span><span class="col-md-6">' . OrderStatusAdmin::getDropDownOrderStatus() . '</span><br />
                        </div>
                        <div class="separator"></div>
                        <div class="col-md-12">
                        <span class="col-md-12 text-start">' . $this->app->getDef('label_text_order_number') . '</span>
                        <span class="col-md-6">' . $this->app->getDef('label_text_start') . HTML::inputField('orders_id_start', '', 'placeholder="10"') . '</span>
                        <span class="col-md-6">' . $this->app->getDef('label_text_end') . HTML::inputField('orders_id_end', '', 'placeholder="50"') . '</span><br />
                        </div>
                        <br /><br />
                        <div class="col-md-12">
                        <span class="col-md-12 text-start">' . $this->app->getDef('text_order_update_status') . '</span>
                        <span class="col-md-6 float-end">' . OrderStatusAdmin::getDropDownOrderStatus('orders_status_update') . '</span>
                        </div>
                        <div class="separator"></div>
                        <div class="col-md-12 text-start">' . $this->app->getDef('text_note_supplier') . '</div>
                        <div>' . HTML::button($this->app->getDef('label_button_print'), null, null, 'secondary', ['newwindow' => 'blank']) . '</div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
      ';

      return $output;
    }
  }