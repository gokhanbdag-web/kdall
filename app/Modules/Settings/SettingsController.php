<?php
declare(strict_types=1);

namespace KodVadAll\Modules\Settings;

use PDO;
use Throwable;

final class SettingsController
{
    private const CURRENCIES = ['TRY', 'USD', 'EUR', 'GBP'];
    private const TIMEZONES = ['Europe/Istanbul', 'Europe/London', 'Europe/Berlin', 'UTC', 'America/New_York', 'Asia/Dubai'];
    private const DATE_FORMATS = ['d.m.Y', 'Y-m-d', 'd/m/Y'];

    public static function handle(PDO $pdo): void
    {
        kd_require_permission('settings.manage');
        $errors = [];
        $saved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kd_csrf_check();
            $data = self::validatedInput($_POST, $errors);
            if (!$errors) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare(
                        'INSERT INTO settings (setting_key,setting_value,updated_at)
                         VALUES (?,?,NOW())
                         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()'
                    );
                    foreach ($data as $key => $value) {
                        $stmt->execute([$key, $value]);
                    }
                    $pdo->commit();
                    kd_audit('settings.updated', 'settings');
                    $saved = true;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('KodVadAll settings module: ' . $e->getMessage());
                    $errors[] = 'Ayarlar kaydedilemedi. Lütfen tekrar deneyin.';
                }
            }
            $settings = $data;
        } else {
            $settings = self::load($pdo);
        }

        $pageTitle = 'İşletme Ayarları';
        ob_start();
        require KD_ROOT . '/app/Views/settings/index.php';
        $content = ob_get_clean();
        require KD_ROOT . '/app/Views/layouts/admin.php';
    }

    private static function validatedInput(array $input, array &$errors): array
    {
        $businessName = trim((string)($input['business_name'] ?? ''));
        $legalName = trim((string)($input['legal_name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $website = trim((string)($input['website'] ?? ''));
        $currency = strtoupper(trim((string)($input['currency'] ?? 'TRY')));
        $timezone = trim((string)($input['timezone'] ?? 'Europe/Istanbul'));
        $dateFormat = trim((string)($input['date_format'] ?? 'd.m.Y'));

        if ($businessName === '' || mb_strlen($businessName) > 190) {
            $errors[] = 'İşletme adı zorunludur ve 190 karakteri geçemez.';
        }
        if ($legalName !== '' && mb_strlen($legalName) > 190) {
            $errors[] = 'Ticari unvan 190 karakteri geçemez.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi girin.';
        }
        if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($website, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $errors[] = 'Web sitesi http:// veya https:// ile başlayan geçerli bir adres olmalıdır.';
        }
        if (!in_array($currency, self::CURRENCIES, true)) {
            $errors[] = 'Desteklenen bir para birimi seçin.';
        }
        if (!in_array($timezone, self::TIMEZONES, true)) {
            $errors[] = 'Desteklenen bir saat dilimi seçin.';
        }
        if (!in_array($dateFormat, self::DATE_FORMATS, true)) {
            $errors[] = 'Geçerli bir tarih biçimi seçin.';
        }

        return [
            'business_name' => $businessName,
            'legal_name' => $legalName,
            'tax_office' => trim((string)($input['tax_office'] ?? '')),
            'tax_number' => trim((string)($input['tax_number'] ?? '')),
            'email' => $email,
            'phone' => trim((string)($input['phone'] ?? '')),
            'website' => $website,
            'address' => trim((string)($input['address'] ?? '')),
            'currency' => $currency,
            'timezone' => $timezone,
            'date_format' => $dateFormat
        ];
    }

    private static function load(PDO $pdo): array
    {
        $settings = [
            'business_name' => 'KodVadAll İşletmesi',
            'legal_name' => '',
            'tax_office' => '',
            'tax_number' => '',
            'email' => '',
            'phone' => '',
            'website' => '',
            'address' => '',
            'currency' => 'TRY',
            'timezone' => 'Europe/Istanbul',
            'date_format' => 'd.m.Y'
        ];
        $rows = $pdo->query('SELECT setting_key,setting_value FROM settings')->fetchAll();
        foreach ($rows as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string)$row['setting_value'];
            }
        }
        return $settings;
    }
}
