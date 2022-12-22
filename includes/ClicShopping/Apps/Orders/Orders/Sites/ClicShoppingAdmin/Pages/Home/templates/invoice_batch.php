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

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\DateTime;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\HTML;
  use ClicShopping\Sites\Shop\Tax;
  use ClicShopping\Sites\Common\PDF;

  use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
  use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;
  use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;

  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
  $CLICSHOPPING_Db = Registry::get('Db');
  $CLICSHOPPING_Language = Registry::get('Language');
  $CLICSHOPPING_Orders = Registry::get('Orders');
  $CLICSHOPPING_Currencies = Registry::get('Currencies');
  $CLICSHOPPING_Mail = Registry::get('Mail');
  $CLICSHOPPING_Hooks = Registry::get('Hooks');
  $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
  $CLICSHOPPING_Address = Registry::get('Address');

  define('FPDF_FONTPATH', CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/font/');
  require_once(CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/fpdf.php');

  $pdf = new \FPDF();

  Registry::set('PDF', new PDF());
  $PDF = Registry::get('PDF');

  if (isset($_POST['orders_id_start'])) {
    $orders_id_start = HTML::sanitize($_POST['orders_id_start']);
  } else {
    $orders_id_start = 0;
  }

  if (isset($_POST['orders_id_end'])) {
  $orders_id_end = HTML::sanitize($_POST['orders_id_end']);
  } else {
    $orders_id_end = 0;
  }

  if (isset($_POST['dropdown_status']) && $_POST['dropdown_status'] > 0) {
    $orders_status_id = HTML::sanitize($_POST['dropdown_status']);
  } else {
    $orders_status_id = 0;
  }

  if (isset($_POST['orders_status_update']) && $_POST['orders_status_update'] > 0) {
    $orders_status_update = HTML::sanitize($_POST['orders_status_update']);
  } else {
    $orders_status_update = 0;
  }

  if (isset($_POST['orders_date'])) {
    $orders_date = HTML::sanitize($_POST['orders_date']);
  } else {
    $orders_date = 0;
  }

  $between_orders_id = '';

  if ($orders_status_id == 0) {

    if ($orders_date != 0) {
      $date_interval = ' AND date_purchased >= (CURDATE() - INTERVAL ' . (int)$orders_date . ' DAY) ';
    } else {
      $date_interval = '';
    }

    if ($orders_id_start != 0 && $orders_id_end != 0) {
      if ($orders_id_start > $orders_id_end) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Orders->getDef('error_orders', 'warning'));
        $CLICSHOPPING_Orders->redirect('Orders');
      }

      $between_orders_id = 'AND orders_id between ' . $orders_id_start . ' and ' . $orders_id_end;
    }

    $QordersInfo = $CLICSHOPPING_Db->prepare('select orders_id,
                                                    customers_id
                                              from :table_orders
                                              where orders_status > 0
                                                    ' . $date_interval . '
                                                    ' . $between_orders_id . '
                                              order by date_purchased asc
                                             ');

    $QordersInfo->execute();

    if ($orders_id_start != 0 && $orders_id_end != 0) {
      $QordersInfo->bindInt(':orders_id_start', $orders_id_start);
      $QordersInfo->bindInt(':orders_id_end', $orders_id_end);
    }
  } else {

    if ($orders_status_id != 0) {
      $orders_status_id = ' orders_status = ' . $orders_status_id;
    } else {
      $orders_status_id = ' orders_status > 0 ';
    }

    if ($orders_date != 0) {
      $date_interval = ' AND date_purchased >= (CURDATE() - INTERVAL ' . (int)$orders_date . ' DAY) ';
    } else {
      $date_interval = '';
    }

    if ($orders_id_start != 0 && $orders_id_end != 0) {
      if ($orders_id_start > $orders_id_end) {
        $CLICSHOPPING_MessageStack->add(ERROR_ORDERS, 'warning');
        $CLICSHOPPING_Orders->redirect('Orders');
      }

      $between_orders_id = 'AND orders_id between ' . $orders_id_start . ' and ' . $orders_id_end;
    }

    $QordersInfo = $CLICSHOPPING_Db->prepare('select orders_id,
                                                      customers_id,
                                                      erp_invoice,
                                                      customers_name,
                                                      customers_email_address
                                                from :table_orders
                                                where ' . $orders_status_id . '
                                                        ' . $date_interval . '
                                                        ' . $between_orders_id . '
                                                order by date_purchased asc
                                               ');

    $QordersInfo->execute();
  }

  while ($QordersInfo->fetch()) {

    Registry::set('Order', new OrderAdmin($QordersInfo->valueInt('orders_id')), true);
    $order = Registry::get('Order');

//*********************************
// update status and email
//*********************************
    // Recuperations de la date de la facture (Voir aussi french.php & invoice.php)
    $QordersHistory = $CLICSHOPPING_Db->prepare('select orders_status_id,
                                                         date_added,
                                                         customer_notified,
                                                         orders_status_invoice_id,
                                                         comments
                                                 from :table_orders_status_history
                                                 where orders_id = :orders_id
                                                 order by date_added desc
                                                 limit 1;
                                                ');

    $QordersHistory->bindInt(':orders_id', $QordersInfo->valueInt('orders_id'));
    $QordersHistory->execute();

    $oID = $QordersInfo->valueInt('orders_id');

// update status
    if ($orders_status_update > 0) {
// verify and update the status if changed
      if (($orders_status_update != $QordersInfo->valueInt('orders_status_id'))) {

        $CLICSHOPPING_Db->save('orders', [
          'orders_status' => (int)$orders_status_update,
          'last_modified' => 'now()'
        ], [
            'orders_id' => $QordersInfo->valueInt('orders_id')
          ]
        );

// insert the modification in the database
        $CLICSHOPPING_Db->save('orders_status_history', ['orders_id' => (int)$QordersInfo->valueInt('orders_id'),
            'date_added' => 'now()',
            'orders_status_id' => (int)$orders_status_update,
            'admin_user_name' => AdministratorAdmin::getUserAdmin(),
          ]
        );

// email
        $template_email_intro_command = TemplateEmailAdmin::getTemplateEmailIntroCommand();
        $template_email_signature = TemplateEmailAdmin::getTemplateEmailSignature();
        $template_email_footer = TemplateEmailAdmin::getTemplateEmailTextFooter();

        $email_subject = $CLICSHOPPING_Orders->getDef('email_text_subject', ['store_name' => STORE_NAME]);
        $email_text = $template_email_intro_command . '<br />' . $CLICSHOPPING_Orders->getDef('email_separator') . '<br /><br />' . $CLICSHOPPING_Orders->getDef('email_text_order_number') . ' ' . $QordersInfo->valueInt('orders_id') . '<br /><br />' . $CLICSHOPPING_Orders->getDef('email_text_invoice_url') . '<br />' . CLICSHOPPING::link(null, 'Account&HistoryInfo&order_id=' . $QordersInfo->valueInt('orders_id')) . '<br /><br /><br />' . $template_email_signature . '<br /><br />' . $template_email_footer;

// Envoie du mail avec gestion des images pour Fckeditor et Imanager.
        $message = html_entity_decode($email_text);
        $message = str_replace('src="/', 'src="' . HTTP::getShopUrlDomain(), $message);
        $CLICSHOPPING_Mail->addHtmlCkeditor($message);
        $CLICSHOPPING_Mail->build_message();
        $from = STORE_OWNER_EMAIL_ADDRESS;

        $CLICSHOPPING_Mail->send($QordersInfo->value('customers_name'), $QordersInfo->value('customers_email_address'), '', $from, $email_subject);

        $CLICSHOPPING_Hooks->call('InvoiceBatch', 'Update');

        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Orders->getDef('success_order_updated'), 'success');
      }
    }

    $orders_history_display = $QordersHistory->valueInt('orders_status_invoice_id');

// Recuperations du nom du type de facture generee
        $QordersStatusInvoice = $CLICSHOPPING_Db->prepare('select orders_status_invoice_id,
                                                                  orders_status_invoice_name,
                                                                  language_id
                                                           from :table_orders_status_invoice
                                                           where orders_status_invoice_id = :orders_status_invoice_id
                                                           and language_id = :language_id
                                                         ');
        $QordersStatusInvoice->bindInt(':orders_status_invoice_id',  (int)$orders_history_display );
        $QordersStatusInvoice->bindInt(':language_id',  (int)$CLICSHOPPING_Language->getId() );
    
        $QordersStatusInvoice->execute();
    
        $order_status_invoice_display = $QordersStatusInvoice->value('orders_status_invoice_name');

// Set the Page Margins
// Marge de la page
    $pdf->SetMargins(10, 2, 6);

// Add the first page
// Ajoute page
    $pdf->AddPage();


    if (DISPLAY_INVOICE_HEADER == 'false') {

// Logo
      if (OrderAdmin::getOrderPdfInvoiceLogo() !== false) {
        $pdf->Image(OrderAdmin::getOrderPdfInvoiceLogo(), 5, 10, 50);
      }

      // Nom de la compagnie
      $pdf->SetX(0);
      $pdf->SetY(10);
      $pdf->SetFont('Arial', 'B', 10);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Ln(0);
      $pdf->Cell(125);
      $pdf->MultiCell(100, 3.5, utf8_decode(STORE_NAME), 0, 'L');

      // Adresse de la compagnie
      $pdf->SetX(0);
      $pdf->SetY(15);
      $pdf->SetFont('Arial', '', 8);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Ln(0);
      $pdf->Cell(125);
      $pdf->MultiCell(100, 3.5, utf8_decode(STORE_NAME_ADDRESS), 0, 'L');

      // Email
      $pdf->SetX(0);
      $pdf->SetY(30);
      $pdf->SetFont('Arial', '', 8);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Ln(0);
      $pdf->Cell(-3);
      $pdf->MultiCell(100, 3.5, utf8_decode($CLICSHOPPING_Orders->getDef('entry_email')) . STORE_OWNER_EMAIL_ADDRESS, 0, 'L');

      // Website
      $pdf->SetX(0);
      $pdf->SetY(34);
      $pdf->SetFont('Arial', '', 8);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Ln(0);
      $pdf->Cell(-3);
      $pdf->MultiCell(100, 3.5, $CLICSHOPPING_Orders->getDef('entry_http_site') . ' ' . CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop'), 0, 'L');
    }


// Ligne de pliage pour mise en enveloppe
    $pdf->Cell(-5);
    $pdf->SetY(103);
    $pdf->SetX(0);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->Cell(3, .1, '', 1, 1, '', 1);

// Cadre pour l'adresse de facturation
    /*
      $pdf->SetDrawColor(0);
      $pdf->SetLineWidth(0.2);
      $pdf->SetFillColor(245);
      $PDF->roundedRect(6, 40, 90, 35, 2, 'DF');
    */
//Draw the invoice address text
// Adresse de facturation
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(11, 44, $CLICSHOPPING_Orders->getDef('entry_sold_to'));
    $pdf->SetX(0);
    $pdf->SetY(47);
    $pdf->Cell(9);
    $pdf->MultiCell(70, 3.3, utf8_decode($CLICSHOPPING_Address->addressFormat($order->customer['format_id'], $order->billing, '', '', "\n")), 0, 'L');

//Draw Box for Delivery Address
// Cadre pour l'adresse de livraison
    /*
      $pdf->SetDrawColor(0);
      $pdf->SetLineWidth(0.2);
      $pdf->SetFillColor(255);
      $PDF->roundedRect(108, 40, 90, 35, 2, 'DF');
    */
//Draw the invoice delivery address text
// Adresse de livraison
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(113, 44, $CLICSHOPPING_Orders->getDef('entry_ship_to'));
    $pdf->SetX(0);
    $pdf->SetY(47);
    $pdf->Cell(111);
    $pdf->MultiCell(70, 3.3, utf8_decode($CLICSHOPPING_Address->addressFormat($order->delivery['format_id'], $order->delivery, '', '', "\n")), 0, 'L');

// Information client
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(10, 85, $CLICSHOPPING_Orders->getDef('entry_customer_information'));

//  email
// Email du client
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(15, 90, $CLICSHOPPING_Orders->getDef('entry_email') . ' ' . $order->customer['email_address']);

//  Customer Number
// Numero de client
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(15, 95, utf8_decode($CLICSHOPPING_Orders->getDef('entry_customer_number')) . ' ' . $QordersInfo->valueInt('customers_id'));

//  Customer phone
// Telephone du client
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(15, 100, utf8_decode($CLICSHOPPING_Orders->getDef('entry_phone')) . ' ' . $order->customer['telephone']);

//Draw Box for Order Number, Date & Payment method
// Cadre du numero de commande, date de commande et methode de paiemenent
    /*
      $pdf->SetDrawColor(0);
      $pdf->SetLineWidth(0.2);
      $pdf->SetFillColor(245);
      $PDF->roundedRect(6, 107, 192, 11, 2, 'DF');
    */
// Order management
    if (($QordersHistory->valueInt('orders_status_invoice_id') == 1)) {
// Display the order
      $temp = str_replace('&nbsp;', ' ', 'No ' . $order_status_invoice_display . ' : ');
      $pdf->Text(10, 113, $temp . $oID);
    } elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 2) {
//Display the invoice
      $temp = str_replace('&nbsp;', ' ', 'No ' . $order_status_invoice_display . ' : ' . DateTime::toDateReferenceShort($QordersHistory->value('date_added')) . 'S');
      $pdf->Text(10, 113, $temp . $oID);
    } elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 3) {
//Display the cancelling
      $temp = str_replace('&nbsp;', ' ', $order_status_invoice_display . ': ');
      $pdf->Text(10, 113, $temp);
    } else {
// Display the order
      $temp = str_replace('&nbsp;', ' ', 'No ' . $order_status_invoice_display . ': ');
      $pdf->Text(10, 113, $temp . $oID);
    }

// Center information order management
    if (($QordersHistory->valueInt('orders_status_invoice_id') == 1)) {
// Display the order
      $temp = str_replace('&nbsp;', ' ', $CLICSHOPPING_Orders->getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
      $pdf->Text(60, 113, $temp . DateTime::toShort($order->info['date_purchased']));
    } elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 2) {
//Display the invoice
      $temp = str_replace('&nbsp;', ' ', $CLICSHOPPING_Orders->getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
      $pdf->Text(60, 113, $temp . DateTime::toShort($order->info['date_purchased']));
    } elseif ($QordersHistory->valueInt('orders_status_invoice_id') == 3) {
//Display the cancelling
      $temp = str_replace('&nbsp;', ' ', '');
      $pdf->Text(10, 113, $temp);
    } else {
// Display the order
      $temp = str_replace('&nbsp;', ' ', $CLICSHOPPING_Orders->getDef('print_order_date') . ' ' . $order_status_invoice_display . ' : ');
      $pdf->Text(60, 113, $temp . DateTime::toShort($order->info['date_purchased']));
    }


//Draw Payment Method Text
    $temp = substr(utf8_decode($order->info['payment_method']), 0, 60);
    $pdf->Text(110, 113, $CLICSHOPPING_Orders->getDef('entry_payment_method') . ' ' . $temp);

// Cadre pour afficher "BON DE COMMANDE" ou "FACTURE"
    $pdf->SetDrawColor(0);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFillColor(245);
    $PDF->roundedRect(108, 32, 90, 7, 2, 'DF');

// Affichage titre "BON DE COMMANDE" ou "FACTURE"
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetY(32);
    $pdf->SetX(108);
    $pdf->MultiCell(90, 7, $order_status_invoice_display, 0, 'C');

// Fields Name position
    $Y_Fields_Name_position = 125;

// Table position, under Fields Name
    $Y_Table_Position = 131;

// Entete du tableau des produits
    $PDF->outputTableHeadingPdf($Y_Fields_Name_position);

    $item_count = 0;

// Boucle sur les produits
// Show the products information line by line
    for ($i = 0, $n = count($order->products); $i < $n; $i++) {

// Quantity
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(6);
      $pdf->MultiCell(9, 6, $order->products[$i]['qty'], 1, 'C');

// Attribut management and Product Name
      $prod_attribs = '';

// Get attribs and concat
      if ((isset($order->products[$i]['attributes'])) && (count($order->products[$i]['attributes']) > 0)) {
        for ($j = 0, $n2 = count($order->products[$i]['attributes']); $j < $n2; $j++) {
          if (!empty($order->products[$i]['attributes'][$j]['reference'])) {
            $reference = $order->products[$i]['attributes'][$j]['reference'] . ' / ';
          }
          $prod_attribs .= " - " . $order->products[$i]['attributes'][$j]['option'] . ' (' . $reference . '): ' . $order->products[$i]['attributes'][$j]['value'];
        }
      }

      $product_name_attrib_contact = $order->products[$i]['name'] . $prod_attribs;

//	product name
// Nom du produit
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(40);
      if (strlen($product_name_attrib_contact) > 40 && strlen($product_name_attrib_contact) < 95) {
        $pdf->SetFont('Arial', '', 6);
        $pdf->MultiCell(103, 6, utf8_decode($product_name_attrib_contact), 1, 'L');
      } else if (strlen($product_name_attrib_contact) > 95) {
        $pdf->SetFont('Arial', '', 6);
        $pdf->MultiCell(103, 6, utf8_decode(substr($product_name_attrib_contact, 0, 95)) . " .. ", 1, 'L');
      } else {
        $pdf->SetFont('Arial', '', 6);
        $pdf->MultiCell(103, 6, utf8_decode($product_name_attrib_contact), 1, 'L');
        $pdf->Ln();
      }

// Model
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(15);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell(25, 6, utf8_decode($order->products[$i]['model']), 1, 'C');

// Taxes
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(143);
      $pdf->MultiCell(15, 6, Tax::displayTaxRateValue($order->products[$i]['tax']), 1, 'C');

// Prix HT
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(158);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell(20, 6, utf8_decode(html_entity_decode($CLICSHOPPING_Currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']))), 1, 'C');
      /*
      // Prix TTC
          $pdf->SetY($Y_Table_Position);
          $pdf->SetX(138);
          $pdf->MultiCell(20,6,$CLICSHOPPING_Currencies->format(Tax::addTax($order->products[$i]['final_price'], $order->products[$i]['tax']), true, $order->info['currency'], $order->info['currency_value']),1,'C');
      */

// Total HT
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(178);
      $pdf->MultiCell(20, 6, utf8_decode(html_entity_decode($CLICSHOPPING_Currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']))), 1, 'C');
      $Y_Table_Position += 6;

      /*
      // Total TTC
          $pdf->SetY($Y_Table_Position);
          $pdf->SetX(178);
          $pdf->MultiCell(20,6,$CLICSHOPPING_Currencies->format(Tax::addTax($order->products[$i]['final_price'], $order->products[$i]['tax']) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']),1,'C');
          $Y_Table_Position += 6;
      */

// Check for product line overflow
      $item_count++;
      if ((is_long($item_count / 32) && $i >= 20) || ($i == 20)) {
        $pdf->AddPage();
// Fields Name position
        $Y_Fields_Name_position = 125;
// Table position, under Fields Name
        $Y_Table_Position = 70;
        Common::outputTableHeadingPdf($Y_Table_Position - 6);

        if ($i == 20) $item_count = 1;
      }
    }

    for ($i = 0, $n = count($order->totals); $i < $n; $i++) {
      $pdf->SetY($Y_Table_Position + 5);
      $pdf->SetX(102);

      $temp = substr($order->totals[$i]['text'], 0, 3);

      if ($temp == '<strong>') {
        $pdf->SetFont('Arial', 'B', 7);
        $temp2 = substr($order->totals[$i]['text'], 3);
        $order->totals[$i]['text'] = substr($temp2, 0, strlen($temp2) - 4);
      }

      $pdf->MultiCell(94, 6, substr(utf8_decode(html_entity_decode($order->totals[$i]['title'])), 0, 30) . ' ' . utf8_decode(html_entity_decode($order->totals[$i]['text'])), 0, 'R');
      $Y_Table_Position += 5;
    }


//Draw the bottom line with invoice text
// Ligne pour le pied de page
    if (DISPLAY_INVOICE_FOOTER == 'false') {
      $pdf->Cell(50);
      $pdf->SetY(-67);
      $pdf->SetDrawColor(153, 153, 153);
      $pdf->Cell(185, .1, '', 1, 1, 'L', 1);


      // Remerciement
      $pdf->SetY(-65);
      $pdf->SetFont('Arial', 'B', 8);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Cell(0, 10, utf8_decode($CLICSHOPPING_Orders->getDef('thank_you_customer')), 0, 0, 'C');

// Proprieties Legal
      $pdf->SetY(-60);
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Cell(0, 10, utf8_decode($CLICSHOPPING_Orders->getDef('reserve_propriete', ['store_name' => STORE_NAME])), 0, 0, 'C');

      $pdf->SetY(-55);
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Cell(0, 10, utf8_decode($CLICSHOPPING_Orders->getDef('reserve_propriete_next')), 0, 0, 'C');

      $pdf->SetY(-50);
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Cell(0, 10, utf8_decode($CLICSHOPPING_Orders->getDef('reserve_propriete_next1', ['url_sell_conditions' => HTTP::getShopUrlDomain() . SHOP_CODE_URL_CONDITIONS_VENTE])), 0, 0, 'C');

// Informations de la compagnie
      if (DISPLAY_DOUBLE_TAXE == 'false') {
        $pdf->SetY(-45);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(INVOICE_RGB);
        $pdf->Cell(0, 10, utf8_decode($CLICSHOPPING_Orders->getDef('entry_info_societe', ['info_societe' => SHOP_CODE_CAPITAL . ' - ' . SHOP_CODE_RCS . ' - ' . SHOP_CODE_APE])), 0, 0, 'C');

        $pdf->SetY(-40);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(INVOICE_RGB);
        $pdf->Cell(0, 10, utf8_decode($CLICSHOPPING_Orders->getDef('entry_info_societe_next', ['tva_intracom' => TVA_SHOP_INTRACOM])), 0, 0, 'C');
      } else {
        $pdf->SetY(-45);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(INVOICE_RGB);
        $pdf->Cell(0, 10, utf8_decode($CLICSHOPPING_Orders->getDef('entry_info_societe1', ['info_societe1' => SHOP_CODE_CAPITAL . ' - ' . SHOP_CODE_RCS . ' - ' . SHOP_CODE_APE])), 0, 0, 'C');

        $pdf->SetY(-40);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(INVOICE_RGB);
        $pdf->Cell(0, 10, utf8_decode($CLICSHOPPING_Orders->getDef('entry_info_societe_next1', ['info_societe1' => TVA_SHOP_PROVINCIAL . ' - ' . TVA_SHOP_FEDERAL])), 0, 0, 'C');
      }

// Autres informations (champ libre) sur la compagnie
      $pdf->SetY(-35);
      $pdf->SetFont('Arial', '', 8);
      $pdf->SetTextColor(INVOICE_RGB);
      $pdf->Cell(0, 10, utf8_decode(SHOP_DIVERS), 0, 0, 'C');
    }
  }

// PDF's created now output the file
  $pdf->Output();