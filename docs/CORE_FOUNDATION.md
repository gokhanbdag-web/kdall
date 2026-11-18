# KodVadAll — Çekirdek altyapı

Bu aşama, tüm iş modüllerinin kullanacağı ortak teknik temeli oluşturur.

## Tamamlanan bileşenler
- Composer gerektirmeyen yerel PHP sınıf yükleyicisi.
- Güvenli oturum çerezleri ve temel HTTP güvenlik başlıkları.
- Veritabanı bağlantısında PDO güvenli varsayılanları.
- Sıralı ve tekrar çalıştırılabilir SQL migration altyapısı.
- Roller, izinler, kullanıcı-rol ve rol-izin ilişkileri.
- Merkezi izin kontrolü ve işlem kaydı servisleri.

## İlk sistem rolleri
- Sahip: tüm izinler.
- Yönetici: tüm izinler.
- Yönetici Yardımcısı: günlük CRM, katalog, satış ve görev işlemleri.
- Personel: temel görüntüleme izinleri.

## Geliştirici kullanımı
`kd_can('customers.manage')` izin kontrolü yapar.
`kd_require_permission('customers.manage')` yetkisiz erişimi 403 ile durdurur.
`kd_audit('customer.created', 'customer', $id)` işlem kaydı oluşturur.

Yeni SQL değişiklikleri `database/migrations/` altında artan numarayla eklenir. Migration'lar ilk veritabanı bağlantısında otomatik ve yalnızca bir kez uygulanır.
