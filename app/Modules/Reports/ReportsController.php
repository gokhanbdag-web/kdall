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
  $sales=$db->prepare("SELECT currency,COUNT(*) orders_count,COALESCE(SUM(grand_total),0) total,COALESCE(AVG(grand_total),0) average FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status NOT IN ('draft','cancelled') GROUP BY currency ORDER BY currency");$sales->execute([$from,$to]);$salesSummary=$sales->fetchAll();
  $purchases=$db->prepare("SELECT currency,COUNT(*) count,COALESCE(SUM(grand_total),0) total FROM purchase_orders WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('partial','received') GROUP BY currency ORDER BY currency");$purchases->execute([$from,$to]);$purchaseSummary=$purchases->fetchAll();
  $finance=$db->prepare("SELECT currency,COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END),0) income,COALESCE(SUM(CASE WHEN direction='debit' THEN amount ELSE 0 END),0) expense FROM financial_transactions WHERE transaction_date BETWEEN ? AND ? AND transaction_type NOT IN ('transfer_in','transfer_out') GROUP BY currency ORDER BY currency");$finance->execute([$from,$to]);$financeSummary=$finance->fetchAll();
  $returns=$db->prepare("SELECT o.currency,COUNT(*) count,COALESCE(SUM(r.refund_total),0) total FROM sales_returns r JOIN orders o ON o.id=r.order_id WHERE DATE(r.created_at) BETWEEN ? AND ? AND r.status<>'rejected' GROUP BY o.currency ORDER BY o.currency");$returns->execute([$from,$to]);$returnSummary=$returns->fetchAll();
  $inventory=$db->query("SELECT COUNT(*) products,COALESCE(SUM(stock_quantity*purchase_price),0) cost_value,COALESCE(SUM(stock_quantity*sale_price),0) sale_value,SUM(CASE WHEN track_stock=1 AND stock_quantity<=min_stock THEN 1 ELSE 0 END) low_stock FROM products WHERE status='active'")->fetch();
  $employeeCount=(int)$db->query("SELECT COUNT(*) FROM employees WHERE employment_status='active'")->fetchColumn();
  $receivable=$db->query("SELECT i.currency,COALESCE(SUM(i.grand_total-COALESCE((SELECT SUM(t.amount) FROM financial_transactions t WHERE t.invoice_id=i.id AND t.currency=i.currency AND t.transaction_type='collection' AND t.direction='credit'),0)),0) total FROM invoices i WHERE i.invoice_type='sales' AND i.status IN ('issued','paid') GROUP BY i.currency ORDER BY i.currency")->fetchAll();
  $payable=$db->query("SELECT i.currency,COALESCE(SUM(i.grand_total-COALESCE((SELECT SUM(t.amount) FROM financial_transactions t WHERE t.invoice_id=i.id AND t.currency=i.currency AND t.transaction_type='supplier_payment' AND t.direction='debit'),0)),0) total FROM invoices i WHERE i.invoice_type='purchase' AND i.status IN ('issued','paid') GROUP BY i.currency ORDER BY i.currency")->fetchAll();
  $overdue=$db->query("SELECT i.id,i.invoice_number,i.invoice_type,i.due_date,i.currency,i.grand_total,COALESCE(c.name,s.name) party_name,ROUND(i.grand_total-COALESCE((SELECT SUM(t.amount) FROM financial_transactions t WHERE t.invoice_id=i.id AND t.currency=i.currency AND ((i.invoice_type='sales' AND t.transaction_type='collection' AND t.direction='credit') OR (i.invoice_type='purchase' AND t.transaction_type='supplier_payment' AND t.direction='debit'))),0)-COALESCE((SELECT SUM(a.amount) FROM financial_advance_allocations a WHERE a.invoice_id=i.id),0),2) remaining,DATEDIFF(CURDATE(),i.due_date) overdue_days FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id LEFT JOIN suppliers s ON s.id=i.supplier_id WHERE i.status='issued' AND i.invoice_type IN ('sales','purchase') AND i.due_date IS NOT NULL AND i.due_date<CURDATE() HAVING remaining>0 ORDER BY overdue_days DESC,i.id DESC LIMIT 200")->fetchAll();
  $overdueSummary=[];foreach($overdue as $invoice){$key=$invoice['invoice_type'].'|'.$invoice['currency'];$overdueSummary[$key]??=['type'=>$invoice['invoice_type'],'currency'=>$invoice['currency'],'count'=>0,'total'=>0.0];$overdueSummary[$key]['count']++;$overdueSummary[$key]['total']+=(float)$invoice['remaining'];}
  $q=$db->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') month,currency,COUNT(*) orders_count,COALESCE(SUM(grand_total),0) total FROM orders WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) AND status NOT IN ('draft','cancelled') GROUP BY DATE_FORMAT(created_at,'%Y-%m'),currency ORDER BY month,currency");$q->execute();$trend=$q->fetchAll();
  $q=$db->prepare("SELECT oi.product_name,o.currency,SUM(oi.quantity) quantity,SUM(oi.line_total) total FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id IS NOT NULL AND DATE(o.created_at) BETWEEN ? AND ? AND o.status NOT IN ('draft','cancelled') GROUP BY oi.product_id,oi.product_name,o.currency ORDER BY quantity DESC,total DESC LIMIT 10");$q->execute([$from,$to]);$topProducts=$q->fetchAll();
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
