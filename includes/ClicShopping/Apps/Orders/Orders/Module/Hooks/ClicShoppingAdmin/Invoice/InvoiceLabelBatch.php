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

  use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

  class InvoiceLabelBatch implements \ClicShopping\OM\Modules\HooksInterface
  {
    protected $app;

    public function __construct()
    {

      if (!Registry::exists('Orders')) {
        Registry::set('Orders', new OrdersApp());
      }

      $this->app = Registry::get('Orders');

      $this->labelFormat = array(array('id' => '0', 'text' => $this->app->getDef('label_text_select')),
        array('id' => '5160', 'text' => '5160 - letter mn (3 col)'),
        array('id' => '5161', 'text' => '5161 - letter mn  (2 col)'),
        array('id' => '5162', 'text' => '5162 - letter mn  (2 col)'),
        array('id' => '5163', 'text' => '5163 - letter mn  (2 col)'),
        array('id' => '5164', 'text' => '5164 - letter in (2 col)'),
        array('id' => '8600', 'text' => '8600 - letter mn (3 col)'),
        array('id' => '7163', 'text' => 'L7163 - A4 mn (2 col)'),
        array('id' => '3422', 'text' => '3422 - A4 mn (3 col)')
      );

      $this->labelDate = array(array('id' => '0', 'text' => $this->app->getDef('label_text_select')),
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
            <style>.modal-dialog {height: 1000px!important;} </style>
            <a data-toggle="modal" data-target="#myModalLableg">' . HTML::button($this->app->getDef('label_button'), null, null, 'secondary') . '</a>
            <div class="modal fade" id="myModalLableg" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h4 class="modal-title text-start" id="myModalLabel">' . $this->app->getDef('label_title') . '</h4>
                  </div>
                  <div class="modal-body text-center">
                    ' . HTML::form('label', $this->app->link('InvoiceLabelBatch')) . '
                      <div class="col-md-12">
                      <span class="col-md-6 text-start">' . $this->app->getDef('label_text_date_order') . '</span><span class="col-md-6">' . HTML::selectMenu('label_date', $this->labelDate) . '</span><br />
                      <span class="col-md-6 text-start">*' . $this->app->getDef('label_text_print_type') . '</span><span class="col-md-6">' . HTML::selectMenu('label_format', $this->labelFormat, 'required aria-required="true" id="label_format"') . '</span><br />
                      </div>

                      <div class="col-md-12">
                      <span class="col-md-12 text-start">' . $this->app->getDef('label_text_order_number') . '</span>
                      <span class="col-md-6">' . $this->app->getDef('label_text_start') . HTML::inputField('orders_id_start', '', 'placeholder="10"') . '</span>
                      <span class="col-md-6">' . $this->app->getDef('label_text_end') . HTML::inputField('orders_id_end', '', 'placeholder="50"') . '</span><br />
                      </div>
                      <div class="col-md-12">' . $this->app->getDef('label_text_note_label') . '</div>
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