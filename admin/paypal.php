<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = input_string('action', 20);
        if ($action === 'save') {
            $mode = strtolower(input_string('mode', 10));
            if (!in_array($mode, ['sandbox', 'live'], true)) {
                throw new AppException('Choose sandbox or live.');
            }
            $clientId = trim((string) ($_POST['client_id'] ?? ''));
            $clientSecret = trim((string) ($_POST['client_secret'] ?? ''));
            $businessEmail = trim((string) ($_POST['business_email'] ?? ''));
            $currency = strtoupper(trim((string) ($_POST['currency'] ?? 'MYR')));
            $appUrl = trim((string) ($_POST['app_url'] ?? ''));
            if ($businessEmail !== '') {
                $businessEmail = validate_email($businessEmail);
            }
            if (($clientId === '') xor ($clientSecret === '')) {
                throw new AppException('Enter both Client ID and Client Secret, or leave both empty and use a business email.');
            }
            if ($clientId === '' && $businessEmail === '') {
                throw new AppException('Add API keys, or a PayPal business / receiving email for website redirect.');
            }
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new AppException('Currency must be a 3-letter code such as MYR.');
            }
            if ($appUrl !== '' && !preg_match('#^https?://[A-Za-z0-9.:-]+(/.*)?$#', $appUrl)) {
                throw new AppException('APP_URL must look like http://127.0.0.1:8091');
            }
            $pairs = [
                'PAYPAL_MODE' => $mode,
                'PAYPAL_CLIENT_ID' => $clientId,
                'PAYPAL_CLIENT_SECRET' => $clientSecret,
                'PAYPAL_BUSINESS_EMAIL' => $businessEmail,
                'PAYPAL_CURRENCY' => $currency,
            ];
            if ($appUrl !== '') {
                $pairs['APP_URL'] = rtrim($appUrl, '/');
            }
            write_env_values($pairs);
            paypal_reload_config();
            flash('success', 'PayPal settings saved. Checkout will open PayPal’s website after Place order.');
        } elseif ($action === 'test') {
            if (!paypal_is_configured()) {
                throw new AppException('Save credentials or a business email first.');
            }
            flash('success', paypal_test_connection());
        } elseif ($action === 'clear') {
            write_env_values([
                'PAYPAL_CLIENT_ID' => '',
                'PAYPAL_CLIENT_SECRET' => '',
                'PAYPAL_BUSINESS_EMAIL' => '',
                'PAYPAL_MODE' => 'sandbox',
            ]);
            paypal_reload_config();
            flash('success', 'PayPal credentials cleared. Checkout will use COD or bank until you add them again.');
        } else {
            throw new AppException('That action is not available.');
        }
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    } catch (Throwable $exception) {
        log_message('paypal', $exception->getMessage());
        flash('error', 'PayPal settings could not be saved.');
    }
    redirect('admin/paypal.php');
}

$ready = paypal_is_configured();
$pageTitle = 'PayPal';
$activeMenu = 'paypal';
require APP_ROOT . '/includes/admin_header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" class="form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <h2>PayPal checkout (opens PayPal website)</h2>
            <p class="muted">After the buyer chooses PayPal, Meridian leaves this site and opens PayPal’s login / pay page — the same pattern as online banking.</p>
            <div class="mb-3">
                <label class="form-label" for="mode">Mode</label>
                <select class="form-select" id="mode" name="mode">
                    <option value="sandbox" <?= (string) config('paypal', 'mode') === 'sandbox' ? 'selected' : '' ?>>Sandbox (testing)</option>
                    <option value="live" <?= (string) config('paypal', 'mode') === 'live' ? 'selected' : '' ?>>Live (real PayPal login page)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="business_email">PayPal receiving email (simple redirect)</label>
                <input class="form-control" id="business_email" name="business_email" type="email" maxlength="120" value="<?= e(old('business_email', paypal_business_email())) ?>" placeholder="you@email.com" autocomplete="off">
                <div class="form-text">Enough for classroom redirect: buyer is posted to PayPal’s website to log in and pay. Use Live mode for the real paypal.com login page.</div>
            </div>
            <hr>
            <p class="muted">Optional REST API (stronger verification). Leave blank if you only use the email above.</p>
            <div class="mb-3">
                <label class="form-label" for="client_id">Client ID</label>
                <input class="form-control" id="client_id" name="client_id" maxlength="200" value="<?= e(old('client_id', (string) config('paypal', 'client_id'))) ?>" autocomplete="off">
            </div>
            <div class="mb-3">
                <label class="form-label" for="client_secret">Client Secret</label>
                <input class="form-control" id="client_secret" name="client_secret" type="password" maxlength="200" value="<?= e(old('client_secret', (string) config('paypal', 'client_secret'))) ?>" autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label class="form-label" for="currency">Currency</label>
                <input class="form-control" id="currency" name="currency" required maxlength="3" value="<?= e(old('currency', (string) config('paypal', 'currency'))) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="app_url">Public site URL (return / cancel)</label>
                <input class="form-control" id="app_url" name="app_url" maxlength="200" placeholder="<?= e(APP_URL) ?>" value="<?= e(old('app_url', (string) (env('APP_URL') ?? APP_URL))) ?>">
                <div class="form-text">PayPal sends buyers back here. Local PHP server: <code>http://127.0.0.1:8091</code>.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-accent" type="submit">Save settings</button>
            </div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test">
                <button class="btn btn-quiet" type="submit" <?= $ready ? '' : 'disabled' ?>>Test connection</button>
            </form>
            <form method="post" data-confirm="Clear PayPal settings from .env?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-danger-soft" type="submit">Clear credentials</button>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <section class="form-card">
            <h2>Status</h2>
            <p>
                <?php if ($ready): ?>
                    <span class="badge-status badge-paid">Ready</span>
                    Mode: <?= e((string) config('paypal', 'mode')) ?>
                    <?php if (paypal_rest_configured()): ?> · API keys<?php endif; ?>
                    <?php if (paypal_business_email() !== ''): ?> · Email redirect<?php endif; ?>
                <?php else: ?>
                    <span class="badge-status badge-pending">Not configured</span>
                    Add a receiving email or API keys so checkout can open PayPal.
                <?php endif; ?>
            </p>
            <p class="muted mb-0">PayPal site: <code><?= e(paypal_web_base()) ?></code></p>
            <p class="muted">Return URL example:<br><code><?= e(APP_URL . '/public/payment_success.php') ?></code></p>
        </section>
        <section class="form-card mt-3">
            <h2>What the buyer sees</h2>
            <ol class="mb-0">
                <li>Choose <strong>PayPal</strong> at checkout.</li>
                <li>Click <strong>Continue to PayPal</strong>.</li>
                <li>Browser opens PayPal’s page (login / pay) — like a bank payment site.</li>
                <li>After paying, PayPal returns to Meridian.</li>
            </ol>
        </section>
    </div>
</div>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
