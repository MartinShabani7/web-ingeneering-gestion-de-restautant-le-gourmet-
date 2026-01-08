<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/security.php';

if (!Security::isLoggedIn() || !Security::isAdmin()) {
    exit('Accès refusé');
}

// Charger l'autoloader de Composer
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use TCPDF;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

$format = $_GET['format'] ?? 'excel';
$search = trim($_GET['search'] ?? '');
$categoryId = $_GET['category_id'] ?? '';
$onlyActive = ($_GET['only_active'] ?? '');

// Récupérer les données (même code que votre API)
$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}
if ($categoryId !== '') {
    $sql .= " AND p.category_id = ?";
    $params[] = (int)$categoryId;
}
if ($onlyActive !== '') {
    $sql .= " AND p.is_available = 1";
}

$sql .= " ORDER BY p.sort_order ASC, p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Appeler la fonction d'export
switch ($format) {
    case 'excel':
        exportToExcel($products);
        break;
    case 'csv':
        exportToCSV($products);
        break;
    case 'pdf':
        exportToPDF($products);
        break;
    case 'word':
        exportToWord($products);
        break;
    default:
        exit('Format non supporté');
}

// FONCTIONS D'EXPORT 
function exportToExcel($products) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Titre du document
    $sheet->setTitle('Produits');
    $sheet->setCellValue('A1', 'Liste des Produits - Le Gourmet');
    $sheet->mergeCells('A1:H1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Date de génération
    $sheet->setCellValue('A2', 'Généré le ' . date('d/m/Y à H:i'));
    $sheet->mergeCells('A2:H2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // En-têtes du tableau
    $headers = ['ID', 'Nom', 'Description', 'Prix (€)', 'Catégorie', 'Disponible', 'Mis en avant', 'Ordre'];
    $column = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($column . '4', $header);
        $sheet->getColumnDimension($column)->setAutoSize(true);
        $column++;
    }
    
    // Style des en-têtes
    $headerStyle = $sheet->getStyle('A4:H4');
    $headerStyle->getFont()->setBold(true);
    $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6E6E6');
    $headerStyle->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // Données des produits
    $row = 5;
    foreach ($products as $product) {
        $sheet->setCellValue('A' . $row, $product['id']);
        $sheet->setCellValue('B' . $row, $product['name']);
        $sheet->setCellValue('C' . $row, $product['description']);
        $sheet->setCellValue('D' . $row, $product['price'])
              ->getStyle('D' . $row)
              ->getNumberFormat()
              ->setFormatCode('#,##0.00€');
        $sheet->setCellValue('E' . $row, $product['category_name'] ?? '-');
        $sheet->setCellValue('F' . $row, $product['is_available'] ? 'Oui' : 'Non');
        $sheet->setCellValue('G' . $row, $product['is_featured'] ? 'Oui' : 'Non');
        $sheet->setCellValue('H' . $row, $product['sort_order']);
        
        // Style des bordures pour chaque ligne
        $sheet->getStyle('A' . $row . ':H' . $row)
              ->getBorders()
              ->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN);
        
        $row++;
    }
    
    // Style pour aligner le texte
    $sheet->getStyle('A5:H' . ($row-1))
          ->getAlignment()
          ->setVertical(Alignment::VERTICAL_TOP)
          ->setWrapText(true);
    
    // Générer le fichier
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="produits_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exportToCSV($products) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="produits_' . date('Y-m-d_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Ajouter BOM UTF-8 pour Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // En-têtes
    fputcsv($output, [
        'ID', 
        'Nom', 
        'Description', 
        'Prix (€)', 
        'Catégorie', 
        'Disponible', 
        'Mis en avant', 
        'Ordre'
    ], ';');
    
    foreach ($products as $product) {
        fputcsv($output, [
            $product['id'],
            $product['name'],
            $product['description'],
            number_format($product['price'], 2, ',', ' '),
            $product['category_name'] ?? '',
            $product['is_available'] ? 'Oui' : 'Non',
            $product['is_featured'] ? 'Oui' : 'Non',
            $product['sort_order']
        ], ';');
    }
    
    fclose($output);
    exit;
}

function exportToPDF($products) {
    // Créer une instance TCPDF
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configuration du document
    $pdf->SetCreator('Le Gourmet');
    $pdf->SetAuthor('Le Gourmet');
    $pdf->SetTitle('Liste des Produits');
    $pdf->SetSubject('Export des produits');
    
    // Marges
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Ajouter une page
    $pdf->AddPage();
    
    // Titre
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Liste des Produits - Le Gourmet', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'Généré le ' . date('d/m/Y à H:i'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // En-têtes du tableau
    $pdf->SetFont('helvetica', 'B', 10);
    $headers = ['ID', 'Nom', 'Prix (€)', 'Catégorie', 'Disponible', 'En avant'];
    $widths = [15, 70, 25, 50, 30, 25];
    
    // Dessiner les en-têtes
    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Données
    $pdf->SetFont('helvetica', '', 9);
    $fill = false;
    
    foreach ($products as $product) {
        // Alternance de couleur pour les lignes
        $pdf->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        $fill = !$fill;
        
        // ID
        $pdf->Cell($widths[0], 8, $product['id'], 1, 0, 'C', true);
        
        // Nom (avec description tronquée)
        $name = $product['name'];
        if (!empty($product['description'])) {
            $description = substr($product['description'], 0, 50);
            if (strlen($product['description']) > 50) {
                $description .= '...';
            }
            $name .= "\n" . $description;
        }
        $pdf->MultiCell($widths[1], 8, $name, 1, 'L', true, 0);
        
        // Prix
        $pdf->Cell($widths[2], 8, number_format($product['price'], 2, ',', ' '), 1, 0, 'R', true);
        
        // Catégorie
        $pdf->Cell($widths[3], 8, $product['category_name'] ?? '-', 1, 0, 'L', true);
        
        // Disponible
        $pdf->Cell($widths[4], 8, $product['is_available'] ? 'Oui' : 'Non', 1, 0, 'C', true);
        
        // Mis en avant
        $pdf->Cell($widths[5], 8, $product['is_featured'] ? 'Oui' : 'Non', 1, 1, 'C', true);
    }
    
    // Statistiques
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 10);
    $pdf->Cell(0, 8, 'Total : ' . count($products) . ' produit(s)', 0, 1);
    
    // Générer le PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="produits_' . date('Y-m-d_His') . '.pdf"');
    header('Cache-Control: max-age=0');
    
    $pdf->Output('produits.pdf', 'D');
    exit;
}

function exportToWord($products) {
    $phpWord = new PhpWord();
    
    // Section principale
    $section = $phpWord->addSection();
    
    // Titre
    $section->addText(
        'Liste des Produits - Le Gourmet',
        ['bold' => true, 'size' => 16],
        ['alignment' => 'center']
    );
    
    $section->addText(
        'Généré le ' . date('d/m/Y à H:i'),
        ['size' => 10, 'color' => '666666'],
        ['alignment' => 'center']
    );
    
    $section->addTextBreak(2);
    
    // Tableau
    $tableStyle = [
        'borderSize' => 6,
        'borderColor' => '999999',
        'cellMargin' => 50
    ];
    $phpWord->addTableStyle('productsTable', $tableStyle);
    $table = $section->addTable('productsTable');
    
    // En-têtes du tableau
    $table->addRow();
    $headers = ['ID', 'Nom', 'Description', 'Prix (€)', 'Catégorie', 'Disponible', 'Mis en avant'];
    
    foreach ($headers as $header) {
        $table->addCell(1500)->addText(
            $header,
            ['bold' => true, 'bgColor' => 'E6E6E6'],
            ['alignment' => 'center']
        );
    }
    
    // Données des produits
    foreach ($products as $product) {
        $table->addRow();
        
        $table->addCell(800)->addText(
            $product['id'],
            null,
            ['alignment' => 'center']
        );
        
        $table->addCell(2000)->addText($product['name']);
        
        $table->addCell(3000)->addText(
            $product['description'] ?: 'Aucune description',
            null,
            ['alignment' => 'justify']
        );
        
        $table->addCell(1200)->addText(
            number_format($product['price'], 2, ',', ' '),
            null,
            ['alignment' => 'right']
        );
        
        $table->addCell(1500)->addText($product['category_name'] ?? '-');
        
        $table->addCell(1200)->addText(
            $product['is_available'] ? 'Oui' : 'Non',
            null,
            ['alignment' => 'center']
        );
        
        $table->addCell(1200)->addText(
            $product['is_featured'] ? 'Oui' : 'Non',
            null,
            ['alignment' => 'center']
        );
    }
    
    // Statistiques
    $section->addTextBreak(2);
    $section->addText(
        'Total : ' . count($products) . ' produit(s) exporté(s)',
        ['italic' => true, 'color' => '666666']
    );
    
    // Sauvegarder le document
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="produits_' . date('Y-m-d_His') . '.docx"');
    header('Cache-Control: max-age=0');
    
    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('php://output');
    exit;
}

?>