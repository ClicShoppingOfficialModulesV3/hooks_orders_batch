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
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\HTML;

  use ClicShopping\Sites\Common\PDF;

  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
  $CLICSHOPPING_Db = Registry::get('Db');
  $CLICSHOPPING_Language = Registry::get('Language');
  $CLICSHOPPING_Orders = Registry::get('Orders');
  $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

  define('FPDF_FONTPATH', CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/font/');
  require_once(CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/fpdf.php');

  $pdf = new \FPDF();

  Registry::set('PDF', new PDF());
  $PDF = Registry::get('PDF');


  if (isset($_POST['dropdown_suppliers']) && !is_null($_POST['dropdown_suppliers'])) {
    $suppliers_id = HTML::sanitize($_POST['dropdown_suppliers']);
  } else {
    $CLICSHOPPING_Orders->redirect();
  }


  if (isset($_POST['orders_id_start']) && !is_null($_POST['orders_id_start'])) {
    $orders_id_start = HTML::sanitize($_POST['orders_id_start']);
  } else {
    $orders_id_start = 0;
  }

  if (isset($_POST['orders_id_end']) && !is_null($_POST['orders_id_end'])) {
    $orders_id_end = HTML::sanitize($_POST['orders_id_end']);
  } else {
    $orders_id_end = 0;
  }

  if (is_null($_POST['dropdown_status']) && $_POST['supplier_date'] != 0) {
    $status_id = HTML::sanitize($_POST['dropdown_status']);
  } else {
    $status_id = 0;
  }

  if (isset($_POST['supplier_date']) && !is_null($_POST['supplier_date'])) {
    $supplier_date = HTML::sanitize($_POST['supplier_date']);
  } else {
    $supplier_date = 0;
  }

  if (isset($_GET['bDS']) || isset($_GET['bED']) || isset($_GET['bID']) || isset($_GET['bOS'])) {
    $QsuppliersProducts = $CLICSHOPPING_Orders->db->prepare('select  s.suppliers_id,
                                                                     s.suppliers_name,
                                                                     s.suppliers_manager,
                                                                     s.suppliers_phone,
                                                                     s.suppliers_email_address,
                                                                     s.suppliers_fax,
                                                                     s.suppliers_address,
                                                                     s.suppliers_suburb,
                                                                     s.suppliers_postcode,
                                                                     s.suppliers_city,
                                                                     s.suppliers_states,
                                                                     s.suppliers_country_id
                                                             from :table_orders_products  op
                                                               left join :table_products  p ON op.products_id = p.products_id
                                                               left join :table_suppliers s ON p.suppliers_id = s.suppliers_id
                                                               left join :table_orders  o ON op.orders_id = o.orders_id
                                                               left join :table_orders_products_attributes opa ON op.orders_products_id = opa.orders_products_id
                                                            where o.date_purchased between :date_scheduled and :expires_date
                                                            and s.suppliers_id = :suppliers_id
                                                            and o.orders_status = :orders_status
                                                            group by
                                                                     opa.products_options,
                                                                     opa.products_options_values
                                                             ');

    $QsuppliersProducts->bindInt(':suppliers_id', (int)$_GET['bID']);
    $QsuppliersProducts->bindInt(':orders_status', (int)$_GET['bOS']);
    $QsuppliersProducts->bindValue(':date_scheduled', $_GET['bDS']);
    $QsuppliersProducts->bindValue(':expires_date', $_GET['bED']);

    $QsuppliersProducts->execute();

    $result_suppliers = $QsuppliersProducts->fetch();
  } else {

    if ($suppliers_id == 0) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Orders->getDef('error_suppliers'), 'warning');
      CLICSHOPPING::redirect(null, 'A&Orders\Orders&Orders');
    } else {
      $suppliers_id1 = ' s.suppliers_id = ' . $suppliers_id;
    }

    if ($supplier_date != 0) {
      $date = 'AND o.date_purchased >= (CURDATE() - INTERVAL ' . (int)$supplier_date . ' DAY), ';
    } else {
      $date = ' ';
    }

    if ($status_id != 0) {
      $orders_status = ' AND o.orders_status = ' . $status_id;
    } else {
      $orders_status = ' AND o.orders_status > 0 ';
    }

    if ($orders_id_start != 0 && $orders_id_end != 0) {
      if ($orders_id_start > $orders_id_end) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Orders->getDef('error_orders'), 'warning');
        $CLICSHOPPING_Orders->redirect('Orders');
      }

      $orders_id_interval = ' AND o.orders_id between ' . $orders_id_start . ' and ' . $orders_id_end;
    } else {
      $orders_id_interval = '';
    }

    $QsuppliersProducts = $CLICSHOPPING_Orders->db->prepare('select  s.suppliers_id,
                                                                     s.suppliers_name,
                                                                     s.suppliers_manager,
                                                                     s.suppliers_phone,
                                                                     s.suppliers_email_address,
                                                                     s.suppliers_fax,
                                                                     s.suppliers_address,
                                                                     s.suppliers_suburb,
                                                                     s.suppliers_postcode,
                                                                     s.suppliers_city,
                                                                     s.suppliers_states,
                                                                     s.suppliers_country_id
                                                             from :table_orders_products  op
                                                               left join :table_products  p ON op.products_id = p.products_id
                                                               left join :table_suppliers s ON p.suppliers_id = s.suppliers_id
                                                               left join :table_orders o ON op.orders_id = o.orders_id
                                                               left join :table_orders_products_attributes opa ON op.orders_products_id = opa.orders_products_id
                                                             where ' . $suppliers_id1 . '
                                                                   ' . $date . '
                                                                   ' . $orders_status . '
                                                                   ' . $orders_id_interval . '
                                                             group by
                                                                     opa.products_options,
                                                                     opa.products_options_values
                                                           ');

    $QsuppliersProducts->execute();

    $result_suppliers = $QsuppliersProducts->fetch();
  }

  if ($result_suppliers !== false) {
  // Classe pdf.php
    $pdf = new \FPDF();

  // Marge de la page
    $pdf->SetMargins(10, 2, 6);

  // Add the first page
  // Ajoute page
    $pdf->AddPage();

    if (is_file(CLICSHOPPING::getConfig('dir_root', 'Shop') . $CLICSHOPPING_Template->getDirectoryShopTemplateImages() . 'logos/invoice/' . INVOICE_LOGO)) {
      $pdf->Image(CLICSHOPPING::getConfig('http_server', 'Shop') . $CLICSHOPPING_Template->getDirectoryShopTemplateImages() . 'logos/invoice/' . INVOICE_LOGO, 5, 10, 50);
    }

  // Cadre pour l'adresse de livraison
    $pdf->SetDrawColor(0);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFillColor(255);
    $PDF->roundedRect(108, 40, 90, 35, 2, 'DF');

  // Adresse de livraison
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
  //  $pdf->Text(113,44,ENTRY_SHIP_TO);
    $pdf->SetX(0);
    $pdf->SetY(47);
    $pdf->Cell(111);

    $pdf->Text(113, 50, utf8_decode($QsuppliersProducts->value('suppliers_name')));
    $pdf->Text(113, 55, utf8_decode($QsuppliersProducts->value('suppliers_address')));
    $pdf->Text(113, 60, utf8_decode($QsuppliersProducts->value('suppliers_suburb')));
    $pdf->Text(113, 65, utf8_decode($QsuppliersProducts->value('suppliers_postcode') . ' ' . $QsuppliersProducts->value('suppliers_city')));
  //$pdf->Text(113,65, utf8_decode($QsuppliersProducts->value('suppliers_states')));
  //  $pdf->Text(113,70, utf8_decode($QsuppliersProducts->value('suppliers_states')));


  // Information fournisseur
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(10, 85, $CLICSHOPPING_Orders->getDef('entry_supplier_information'));


  // Manager du fournisseur
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(113, 85, $CLICSHOPPING_Orders->getDef('entry_manager') . ' ' . utf8_decode($QsuppliersProducts->value('suppliers_manager')));

  // Email du fournisseur
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(113, 90, $CLICSHOPPING_Orders->getDef('entry_email') . ' ' . $QsuppliersProducts->value('suppliers_email_address'));

  // Manager
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(113, 95, $CLICSHOPPING_Orders->getDef('entry_phone') . ' ' . $QsuppliersProducts->value('suppliers_phone'));

  // Telephone du client
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0);
    $pdf->Text(113, 100, $CLICSHOPPING_Orders->getDef('entry_fax') . ' ' . $QsuppliersProducts->value('suppliers_fax'));


  // Cadre du numero de fournisseur, date debut analyse et date fin analyse
    $pdf->SetDrawColor(0);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFillColor(245);
    $PDF->roundedRect(6, 107, 192, 11, 2, 'DF');

  // Numero de commande ou de facture
  // Date de commande ou de facture
  // Methode de paiement

    $pdf->Text(10, 113, html_entity_decode($CLICSHOPPING_Orders->getDef('entry_suppliers_number')) . '  ' . (int)$_GET['bID']);
    $pdf->Text(65, 113, html_entity_decode($CLICSHOPPING_Orders->getDef('start_analyse')) . ' ' . $_GET['bDS']);
    $pdf->Text(130, 113, $CLICSHOPPING_Orders->getDef('end_analyse') . ' ' . $_GET['bED']);

  // Cadre pour afficher du Titre
    $pdf->SetDrawColor(0);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFillColor(245);
    $PDF->roundedRect(108, 32, 90, 7, 2, 'DF');

  // Affichage du titre
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetY(32);
    $pdf->SetX(108);
    $pdf->MultiCell(90, 7, $CLICSHOPPING_Orders->getDef('print_suppliers_title') . ' ' . utf8_decode($QsuppliersProducts->value('suppliers_name')), 0, 'C');

  // Fields Name position
    $Y_Fields_Name_position = 125;

  // Table position, under Fields Name
    $Y_Table_Position = 131;

  // Entete du tableau des produits a commander
    $PDF->outputTableSuppliers($Y_Fields_Name_position);
  //  $PDF->outputTableHeadingPdf($Y_Fields_Name_position);

    if (isset($_GET['bDS']) || isset($_GET['bED']) || isset($_GET['bID']) || isset($_GET['bOS'])) {
      $QuppliersProducts1 = $CLICSHOPPING_Orders->db->prepare('SELECT s.suppliers_id,
                                                              SUM(op.products_quantity) AS sum_qty,
                                                              s.suppliers_name,
                                                              op.products_name,
                                                              op.products_model,
                                                              opa.products_options,
                                                              opa.products_options_values
                                                        FROM :table_orders_products op
                                                        LEFT JOIN :table_products p ON op.products_id = p.products_id
                                                        LEFT JOIN :table_suppliers s ON p.suppliers_id = s.suppliers_id
                                                        LEFT JOIN :table_orders o ON op.orders_id = o.orders_id
                                                        left join :table_orders_products_attributes opa ON op.orders_products_id = opa.orders_products_id
                                                        WHERE o.date_purchased BETWEEN :date_scheduled AND :expires_date
                                                        AND s.suppliers_id = :suppliers_id
                                                        AND o.orders_status = :orders_status
                                                        group by
                                                               op.products_model,
                                                               op.products_name,
                                                               opa.products_options,
                                                               opa.products_options_values
                                                        order by
                                                        op.products_model,
                                                        op.products_name
                                                      ');

      $QuppliersProducts1->bindValue(':date_scheduled', $_GET['bDS']);
      $QuppliersProducts1->bindValue(':expires_date', $_GET['bED']);
      $QuppliersProducts1->bindInt(':suppliers_id', $_GET['bID']);
      $QuppliersProducts1->bindInt(':orders_status', $_GET['bOS']);

      $QuppliersProducts1->execute();

    } else {

      if ($suppliers_id == 0) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Orders->getDef('error_suppliers'), 'warning');
        $CLICSHOPPING_Orders->redirect('Orders');
      } else {
        $suppliers_id1 = ' s.suppliers_id = ' . $suppliers_id;
      }

      if ($supplier_date != 0) {
        $date = ' AND o.date_purchased >= (CURDATE() - INTERVAL ' . $supplier_date . ' DAY) ';
      }

      if ($status_id != 0) {
        $orders_status = ' AND o.orders_status = ' . $status_id;
      }

      if ($orders_id_start != 0 && $orders_id_end != 0) {
        if ($orders_id_start > $orders_id_end) {
          $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Orders->getDef('error_orders'), 'warning');
          $CLICSHOPPING_Orders->redirect('Orders');
        }

        $orders_id_interval = ' AND o.orders_id between ' . $orders_id_start . ' and ' . $orders_id_end;
      }

      $QuppliersProducts1 = $CLICSHOPPING_Orders->db->prepare('SELECT s.suppliers_id,
                                                              SUM(op.products_quantity) AS sum_qty,
                                                              s.suppliers_name,
                                                              op.products_name,
                                                              op.products_model,
                                                              opa.products_options,
                                                              opa.products_options_values
                                                        FROM :table_orders_products op
                                                        LEFT JOIN :table_products p ON op.products_id = p.products_id
                                                        LEFT JOIN :table_suppliers s ON p.suppliers_id = s.suppliers_id
                                                        LEFT JOIN :table_orders o ON op.orders_id = o.orders_id
                                                        left join :table_orders_products_attributes opa ON op.orders_products_id = opa.orders_products_id
                                                       WHERE ' . $suppliers_id1 . '
                                                             ' . $date . '
                                                             ' . $orders_status . '
                                                             ' . $orders_id_interval . '
                                                       group by
                                                             op.products_model,
                                                             op.products_name,
                                                             opa.products_options,
                                                             opa.products_options_values
                                                       order by
                                                                op.products_model,
                                                                op.products_name
                                                     ');

      $QuppliersProducts1->execute();
    }

    while ($QuppliersProducts1->fetch()) {
  // Quantite
      $pdf->SetFont('Arial', '', 7);
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(6);
      $pdf->MultiCell(9, 6, $QuppliersProducts1->valueInt('sum_qty'), 1, 'C');

  // products model
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(15);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell(25, 6, utf8_decode($QuppliersProducts1->value('products_model')), 1, 'C');

  // products name
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(40);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell(65, 6, utf8_decode($QuppliersProducts1->value('products_name')), 1, 'C');

  // products options
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(105);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell(45, 6, utf8_decode($QuppliersProducts1->value('products_options')), 1, 'C');


  // products options values
      $pdf->SetY($Y_Table_Position);
      $pdf->SetX(150);
      $pdf->SetFont('Arial', '', 7);
      $pdf->MultiCell(45, 6, $QuppliersProducts1->value('products_options_values'), 1, 'C');
      $Y_Table_Position += 6;

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

  // PDF's created now output the file
    $pdf->Output();
  }