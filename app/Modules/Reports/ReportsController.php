<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Reports;
use PDO;
use RuntimeException;

final class ReportsController
{
 public static function handle(PDO $db):void
 {
  kd_require_permission('reports.view');$today=date('Y-m-d');$from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??$today);
  foreach([$from,$to] as $date)if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Rapor tarih aralığı geçersiz.');
  if($from>$to)[$from,$to]=[$to,$from];
  if(($_GET['action']??'')==='export'){self::export($db,(string)($_GET['report']??'sales'),$from,$to);}
  $sales=$db->prepare("SELECT COUNT(*) orders_count,COALESCE(SUM(grand_total),0) total,COALESCE(AVG(grand_total),0) average FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status NOT IN ('draft','cancelled')");$sales->execute([$from,$to]);$salesSummary=$sales->fetch();
  $purchases=$db->prepare("SELECT COUNT(*) count,COALESCE(SUM(grand_total),0) total FROM purchase_orders WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('partial','received')");$purchases->execute([$from,$to]);$purchaseSummary=$purchases->fetch();
  $finance=$db->prepare("SELECT COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END),0) income,COALESCE(SUM(CASE WHEN direction='debit' THEN amount ELSE 0 END),0) expense FROM financial_transactions WHERE transaction_date BETWEEN ? AND ? AND transaction_type NOT IN ('transfer_in','transfer_out')");$finance->execute([$from,$to]);$financeSummary=$finance->fetch();
  $returns=$db->prepare("SELECT COUNT(*) count,COALESCE(SUM(refund_total),0) total FROM sales_returns WHERE DATE(created_at) BETWEEN ? AND ? AND status<>'rejected'");$returns->execute([$from,$to]);$returnSummary=$returns->fetch();
  $inventory=$db->query("SELECT COUNT(*) products,COALESCE(SUM(stock_quantity*purchase_price),0) cost_value,COALESCE(SUM(stock_quantity*sale_price),0) sale_value,SUM(CASE WHEN track_stock=1 AND stock_quantity<=min_stock THEN 1 ELSE 0 END) low_stock FROM products WHERE status='active'")->fetch();
  $employeeCount=(int)$db->query("SELECT COUNT(*) FROM employees WHERE employment_status='active'")->fetchColumn();
  $receivable=(float)$db->query("SELECT COALESCE(SUM(i.grand_total-COALESCE((SELECT SUM(t.amount) FROM financial_transactions t WHERE t.invoice_id=i.id),0)),0) FROM invoices i WHERE i.invoice_type='sales' AND i.status IN ('issued','paid')")->fetchColumn();
  $payable=(float)$db->query("SELECT COALESCE(SUM(i.grand_total-COALESCE((SELECT SUM(t.amount) FROM financial_transactions t WHERE t.invoice_id=i.id),0)),0) FROM invoices i WHERE i.invoice_type='purchase' AND i.status IN ('issued','paid')")->fetchColumn();
  $q=$db->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') month,COUNT(*) orders_count,COALESCE(SUM(grand_total),0) total FROM orders WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) AND status NOT IN ('draft','cancelled') GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month");$q->execute();$trend=$q->fetchAll();
  $q=$db->prepare("SELECT oi.product_name,SUM(oi.quantity) quantity,SUM(oi.line_total) total FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status NOT IN ('draft','cancelled') GROUP BY oi.product_id,oi.product_name ORDER BY total DESC LIMIT 10");$q->execute([$from,$to]);$topProducts=$q->fetchAll();
  $lowStock=$db->query("SELECT id,name,sku,stock_quantity,min_stock,unit FROM products WHERE status='active' AND track_stock=1 AND stock_quantity<=min_stock ORDER BY stock_quantity ASC LIMIT 20")->fetchAll();
  $recent=$db->prepare("SELECT o.id,o.order_number,o.created_at,o.status,o.grand_total,o.currency,c.name customer_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.id DESC LIMIT 15");$recent->execute([$from,$to]);$recentOrders=$recent->fetchAll();
  $pageTitle='Raporlar & Analiz';ob_start();require KD_ROOT.'/app/Views/reports/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
 private static function export(PDO $db,string $report,string $from,string $to):never
 {
  $queries=[
   'sales'=>['SELECT o.order_number,o.created_at,o.status,c.name customer_name,o.subtotal,o.tax_total,o.grand_total,o.currency FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE DATE(o.created_at) BETWEEN ? AND ? ORDER BY o.id DESC',['Sipariş No','Tarih','Durum','Müşteri','Ara Toplam','KDV','Genel Toplam','Para Birimi']],
   'purchases'=>['SELECT p.purchase_number,p.created_at,p.status,s.name supplier_name,p.subtotal,p.tax_total,p.grand_total,p.currency FROM purchase_orders p JOIN suppliers s ON s.id=p.supplier_id WHERE DATE(p.created_at) BETWEEN ? AND ? ORDER BY p.id DESC',['Sipariş No','Tarih','Durum','Tedarikçi','Ara Toplam','KDV','Genel Toplam','Para Birimi']],
   'finance'=>['SELECT t.transaction_date,t.transaction_type,t.direction,t.amount,t.currency,a.name account_name,t.category,t.reference_no,t.description FROM financial_transactions t JOIN financial_accounts a ON a.id=t.account_id WHERE t.transaction_date BETWEEN ? AND ? ORDER BY t.id DESC',['Tarih','İşlem','Yön','Tutar','Para Birimi','Hesap','Kategori','Referans','Açıklama']],
   'stock'=>["SELECT name,sku,stock_quantity,min_stock,purchase_price,sale_price,unit FROM products WHERE status='active' ORDER BY name",['Ürün','SKU','Stok','Minimum Stok','Alış Fiyatı','Satış Fiyatı','Birim']]
  ];
  if(!isset($queries[$report]))throw new RuntimeException('Dışa aktarılacak rapor türü geçersiz.');
  [$sql,$headers]=$queries[$report];$stmt=$db->prepare($sql);$stmt->execute($report==='stock'?[]:[$from,$to]);
  header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="kodvadall-'.$report.'-'.$from.'-'.$to.'.csv"');header('X-Content-Type-Options: nosniff');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$headers,';');
  while($row=$stmt->fetch(PDO::FETCH_NUM)){foreach($row as &$value){$value=(string)($value??'');if(preg_match('/^[=+\-@\t\r]/',$value))$value="'".$value;}unset($value);fputcsv($out,$row,';');}
  fclose($out);exit;
 }
}
