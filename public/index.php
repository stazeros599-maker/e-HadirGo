<?php
// php -S localhost:8000 -t public

declare(strict_types=1);

/**
 * Front controller. Every request is index.php?r=<route>.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

require APP_ROOT . '/app/controllers/auth.php';
require APP_ROOT . '/app/controllers/staff.php';
require APP_ROOT . '/app/controllers/settings.php';
require APP_ROOT . '/app/controllers/supervisor.php';
require APP_ROOT . '/app/controllers/admin.php';

set_exception_handler(static function (Throwable $e): void {
    error_log('[e-HadirGo] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
    }

    echo '<!doctype html><meta charset="utf-8"><title>503</title>'
        . '<div style="font:16px/1.5 system-ui;padding:48px;max-width:640px;margin:0 auto">'
        . '<h1 style="font-size:40px;margin:0 0 8px">503</h1>'
        . '<p>Perkhidmatan tidak tersedia. Sila cuba sebentar lagi.</p>'
        . '<p style="color:#666">Service unavailable. Please try again.</p></div>';
});

$route = (string) ($_GET['r'] ?? 'home');

$routes = [
    'health'                  => 'json_out',
    'login'                   => 'auth_login',
    'signup'                  => 'auth_signup',
    'verify'                  => 'auth_verify',
    'resend'                  => 'auth_resend_otp',
    'forgot'                  => 'auth_forgot',
    'reset'                   => 'auth_reset',
    'logout'                  => 'auth_logout',

    'dashboard'               => 'staff_dashboard',
    'assignment'              => 'staff_assignment',
    'checkin'                 => 'staff_checkin',
    'checkout'                => 'staff_checkout',
    'records'                 => 'staff_records',

    'settings'                => 'settings_page',
    'settings.profile'        => 'settings_profile',
    'settings.unit'           => 'settings_unit',
    'settings.language'       => 'settings_language',
    'settings.password'       => 'settings_password',
    'settings.telegram.link'  => 'settings_telegram_link',
    'settings.telegram.unlink' => 'settings_telegram_unlink',
    'settings.test'           => 'settings_test_email',
    'settings.test.email'     => 'settings_test_email',
    'settings.test.telegram'  => 'settings_test_telegram',

    'sup.assignments'         => 'sup_assignments',
    'sup.form'                => 'sup_form',
    'sup.places'              => 'sup_places',
    'sup.map'                 => 'sup_map',
    'sup.review'              => 'sup_review',
    'sup.decide'              => 'sup_decide',
    'sup.report'              => 'sup_report',
    'sup.report.csv'          => 'sup_report_csv',
    'sup.report.pdf'          => 'sup_report_pdf',

    'admin.users'             => 'admin_users',
    'admin.roles'             => 'admin_roles',
    'admin.config'            => 'admin_config',
    'admin.unit.save'         => 'admin_unit_save',
    'admin.location.save'     => 'admin_location_save',
    'admin.logs'              => 'admin_logs',

    'tg.webhook'              => 'telegram_webhook',
];

if ($route === 'home') {
    redirect(current_user() !== null ? url('dashboard') : url('login'));
}

if ($route === 'health') {
    json_out(db_health());
}

if (!isset($routes[$route])) {
    http_response_code(404);
    page('error', ['title' => '404', 'code' => 404, 'message' => t('err.notfound')]);

    return;
}

$handler = $routes[$route];
$handler();
