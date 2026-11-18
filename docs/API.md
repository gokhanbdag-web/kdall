# KodVadAll API ve Webhook

## Kimlik doğrulama

Her istekte şu başlığı gönderin:

`Authorization: Bearer API_ANAHTARINIZ`

Anahtarlar yönetim panelindeki **API & Webhook** bölümünden oluşturulur. Ham anahtar yalnızca oluşturulduğu anda gösterilir.

## Kaynaklar

- `GET /api.php?resource=products` — ürünleri okur (`products:read`)
- `GET /api.php?resource=customers` — müşterileri okur (`customers:read`)
- `GET /api.php?resource=orders` — siparişleri okur (`orders:read`)
- `POST /api.php?resource=orders` — taslak sipariş oluşturur (`orders:write`)

Liste uç noktalarında `limit` parametresi 1–100 aralığındadır. API anahtarı başına dakikada 120 istek sınırı uygulanır.

### Sipariş oluşturma örneği

```bash
curl -X POST "https://YOUR-DOMAIN/api.php?resource=orders" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"customer_id":12,"items":[{"product_id":5,"quantity":2}],"notes":"ERP aktarımı"}'
```

Siparişler güvenlik amacıyla `draft` durumunda oluşturulur; stok düşümü yönetim panelinden sipariş onaylandığında yapılır.

## Webhook doğrulama

Webhook isteği JSON gövdesi ve şu başlıklarla gönderilir:

- `X-KodVadAll-Event`: olay adı
- `X-KodVadAll-Signature`: `sha256=...` biçiminde HMAC-SHA256

İmza, ham istek gövdesi ve panelde bir kez gösterilen webhook gizli anahtarıyla hesaplanır. Teslimat başarısız olursa otomasyon cron'u artan aralıklarla en fazla beş deneme yapar.

Desteklenen olaylar: `order.created`, `order.updated`, `payment.paid`, `payment.failed`, `return.created`.

## Zamanlanmış teslimatlar

Sunucunun cron alanına şu komutu 5–10 dakikada bir ekleyin:

```bash
/usr/local/lsws/lsphp85/bin/php /home/ACCOUNT/kdall.kodvadisi.com.tr/public_html/cron/automation.php
```

Gerçek sunucu kullanıcı adı ve kurulum yolu ile değiştirin.
