<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$dash=file_get_contents($root.'/app/Views/sales/dashboard.php');
$head=file_get_contents($root.'/app/Views/layout/header.php');
$ctl=file_get_contents($root.'/app/Controllers/SalesController.php');
$help=file_get_contents($root.'/app/Views/help/sales.php');
$checks=[
    strpos($dash,'data-open-sales-bulk-submit')!==false,
    strpos($dash,'data-open-sales-submit')===false,
    strpos($dash,'id="salesSubmitModal"')===false,
    strpos($head,'/sales/bulk-submit')!==false,
    strpos($head,'href="<?= Util::e($base) ?>/sales/submit"')===false,
    strpos($ctl,"\$this->redirect('/sales/bulk-submit')")!==false,
    strpos($help,'single-item Submit Post entry was removed')!==false,
];
foreach($checks as $ok){ if(!$ok){fwrite(STDERR,"V0.2.127 bulk-only submit contract failed.\n"); exit(1);} }
echo "OK Sales Bulk Submit-only workflow v0.2.127\n";
