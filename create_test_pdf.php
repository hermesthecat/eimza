<?php
if (isset($_POST['create'])) {
    require_once('tcpdf/tcpdf.php');

    // Initialize TCPDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Test Creator');
    $pdf->SetAuthor('Test Author');
    $pdf->SetTitle('Test PDF');

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 16);

    // Add some content
    $pdf->Cell(0, 10, 'Bu bir test PDF dosyasıdır', 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->MultiCell(0, 10, 'Bu PDF dosyası, imzalama özelliğini test etmek için oluşturulmuştur. İmzalandıktan sonra, bu metnin altında imza bilgileri görünmelidir.', 0, 'L');

    // Save PDF
    $pdf->Output('uploads/test.pdf', 'F');

    echo '<div style="color: green; margin: 20px;">Test PDF başarıyla oluşturuldu: uploads/test.pdf</div>';
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Test PDF Oluştur</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .button {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <h1>Test PDF Oluştur</h1>
    <p>Bu sayfayı kullanarak test için bir PDF dosyası oluşturabilirsiniz.</p>
    <form method="post">
        <input type="submit" name="create" value="PDF Oluştur" class="button">
    </form>
</body>

</html>