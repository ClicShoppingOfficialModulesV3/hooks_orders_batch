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

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\CLICSHOPPING;
  
  $CLICSHOPPING_Orders = Registry::get('Orders');
  $CLICSHOPPING_Address = Registry::get('Address');
  $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

  define('FPDF_FONTPATH', CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/font/');
  require_once(CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/fpdf.php');

  require_once(CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/label/PDF_Label.php');

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


  if (isset($_POST['label_format'])) {
    $label_format = HTML::sanitize($_POST['label_format']);
  } else {
    $label_format = '7163';
  }

  if (isset($_POST['label_date'])) {
    $label_date = HTML::sanitize($_POST['label_date']);
  } else {
    $label_date = 0;
  }

  $error = false;

// Standard format
  if ($label_format == 0) {
    $error = true;
    $CLICSHOPPING_MessageStack->add('Format Error', 'warning');
  }

// order
  if (!empty($orders_id_start) && !empty($orders_id_end)) {
    if ($orders_id_start > 0 && $orders_id_start > $orders_id_end) {
      $error = true;
      $CLICSHOPPING_MessageStack->add('Date Error', 'warning');
    }
  }

  if ($error === true) {
    $CLICSHOPPING_Orders->redirect('Orders');
  }

  $pdf = new PDF_Label($label_format);

  $pdf->AddPage();

  if ($label_date == 1) {
// today dernière 24 hr
    $Qorder = $CLICSHOPPING_Orders->db->prepare('select orders_id
                                                 from :table_orders
                                                 where last_modified >= (CURDATE() - INTERVAL 1 DAY)
                                                ');
    $Qorder->execute();

// yesterday
  } elseif ($label_date == 2) {
// today dernière 48 hr
    $Qorder = $CLICSHOPPING_Orders->db->prepare('select orders_id
                                                 from :table_orders
                                                 where last_modified >= (CURDATE() - INTERVAL 2 DAY)
                                                ');
    $Qorder->execute();
  } elseif ($label_date == 3) {
// today dernière 72 hr
    $Qorder = $CLICSHOPPING_Orders->db->prepare('select orders_id
                                                 from :table_orders
                                                 where last_modified >= (CURDATE() - INTERVAL 3 DAY)
                                                ');
    $Qorder->execute();

  } elseif ($label_date == 7) {
// week
    $Qorder = $CLICSHOPPING_Orders->db->prepare('select orders_id
                                                 from :table_orders
                                                 where last_modified >= (CURDATE() - INTERVAL 7 DAY)
                                                ');
    $Qorder->execute();
  }

// DATE
  if (!empty($orders_id_start) && !empty($orders_id_end)) {
    $Qorder = $CLICSHOPPING_Orders->db->prepare('select orders_id
                                                  from :table_orders
                                                  where orders_id >= ' . $orders_id_start . '
                                                  and orders_id <= ' . $orders_id_end . '
                                                 ');
    $Qorder->execute();
  }

  if ($label_date == 0 && empty($orders_id_start) && empty($orders_id_end)) {
    $Qorder = $CLICSHOPPING_Orders->db->prepare('select orders_id
                                                from :table_orders
                                                ');
    $Qorder->execute();
  }


  while ($Qorder->fetch()) {
    $Qdelivery = $CLICSHOPPING_Orders->db->prepare('select *
                                                     from :table_orders
                                                     where orders_id = :orders_id
                                                   ');
    $Qdelivery->bindInt(':orders_id', $Qorder->valueInt('orders_id'));

    $Qdelivery->execute();

    $delivery = ['orders_id' => 'Orders : ' . $Qdelivery->valueInt('orders_id'),
      'name' => $Qdelivery->value('billing_name'),
      'company' => $Qdelivery->value('billing_company'),
      'street_address' => $Qdelivery->value('billing_street_address'),
      'suburb' => $Qdelivery->value('billing_suburb'),
      'city' => $Qdelivery->value('billing_city'),
      'postcode' => $Qdelivery->value('billing_postcode'),
      'state' => $Qdelivery->value('billing_state'),
      'country' => $Qdelivery->value('billing_country'),
      'title' => $Qdelivery->value('billing_country'),
      'format_id' => $Qdelivery->valueInt('billing_address_format_id')
    ];

    $address = STORE_NAME . ' - ' . $delivery['orders_id'] . "\n\n";
    $address .= utf8_decode($CLICSHOPPING_Address->addressFormat($delivery['format_id'], $delivery, '', '', "\n"));

    $pdf->Add_Label($address);
  }

  //sortie du fichier
//  $pdf->Output( CLICSHOPPING::BASE_DIR . 'Work/PDF/invoice_label.pdf','F');

// PDF's created now output the file
  $pdf->Output();