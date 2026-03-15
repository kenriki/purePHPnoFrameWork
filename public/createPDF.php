<?php
//require 'vendor/autoload.php';
// require __DIR__ . '/fpdf.php';  // vendor ではなく、純粋な1ファイル

// $pdf = new FPDF();
// $pdf->AddPage();
// $pdf->SetFont('Arial', 'B', 16);

// // 1ページ目
// $pdf->Cell(40, 10, 'Hello World!');

// // 2ページ目
// $pdf->AddPage();
// $pdf->SetTextColor(255, 0, 0);
// $pdf->Cell(40, 10, 'Hello Pure PHP PDF');

// // ▼ ブラウザに直接表示
// $pdf->Output('I', 'hello_world.pdf');
// exit;

?>

<?php
ob_clean();
require __DIR__ . '/tfpdf.php';

$pdf = new tFPDF();
$pdf->AddPage();

// 日本語フォントを追加（UTF-8）
$pdf->AddFont('NotoSansJP', '', 'NotoSansJP-VariableFont_wght.ttf', true);
$pdf->SetFont('NotoSansJP', '', 14);

// JSON の UTF-8 をそのまま書ける
$pdf->MultiCell(0, 10, $pdfData['title']);
$pdf->Ln(10);

foreach ($pdfData['sections'] as $section) {
    if ($section['type'] === 'text') {
        $pdf->MultiCell(0, 8, $section['content']);
        $pdf->Ln(5);
    }
}

// $pdf->Output('I', 'json_page.pdf');
// ★ ここでブラウザのタイトル名を変更
$filename = rawurlencode($pdfData['title']) . '.pdf';
header("Content-Disposition: inline; filename*=UTF-8''{$filename}");
$pdf->Output('I', $pdfData['title'] . '.pdf');
exit;

exit;
?>



