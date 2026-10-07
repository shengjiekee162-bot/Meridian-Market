<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect(home_for_role(current_user()['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
            flash('success', 'Account created. You can sign in now.');
            redirect('public/login.php');
        }
        $name = validate_person_name(input_string('name', 80));
        $email = validate_email(input_string('email', 120));
        $phone = validate_phone(input_string('phone', 20));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        validate_password($password);
        if (!hash_equals($password, $confirm)) {
            throw new AppException('Password confirmation does not match.');
        }
        if (!isset($_POST['terms'])) {
            throw new AppException('Agree to the marketplace terms to create an account.');
        }
        $exists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            throw new AppException('An account with that email already exists.');
        }
        $insert = db()->prepare(
            'INSERT INTO users (name, email, password, phone, address, role, status, must_change_password, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, \'customer\', \'active\', 0, NOW(), NOW())'
        );
        $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, '']);
        send_app_mail($email, 'Welcome to Meridian Market', "Hello {$name},\n\nYour customer account is ready. Add your address in Profile when you are ready to check out.\n\n" . APP_NAME);
        flash('success', 'Account created. Sign in, then add your address in Profile before checkout.');
        redirect('public/login.php');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    } catch (Throwable $exception) {
        log_message('auth', $exception->getMessage());
        flash('error', 'The account could not be created.');
        set_old($_POST);
    }
}

$pageTitle = 'Create account';
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container">
    <div class="auth-wrap form-card">
        <p class="eyebrow">Customers</p>
        <h1>Create an account</h1>
        <form method="post">
            <?= csrf_field() ?>
            <div class="hp-field" aria-hidden="true">
                <label for="company_website">Company website</label>
                <input id="company_website" name="company_website" tabindex="-1" autocomplete="off">
            </div>
            <div class="mb-3">
                <label class="form-label" for="name">Full name</label>
                <input class="form-control" id="name" name="name" required maxlength="80" value="<?= e(old('name')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" required maxlength="120" value="<?= e(old('email')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" required maxlength="20" value="<?= e(old('phone')) ?>">
            </div>
            <p class="muted small">You can add your shipping address later in Profile or at checkout.</p>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input class="form-control" id="password" name="password" type="password" required minlength="8" maxlength="72" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,72}" title="Use 8 or more characters with upper and lower case letters, a number, and a symbol.">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Confirm password</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required maxlength="72">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="terms" name="terms" value="1" required>
                <label class="form-check-label" for="terms">I agree to shop under the sample marketplace terms: orders, stock, and payments are handled as described at checkout.</label>
            </div>
            <button class="btn btn-accent w-100" type="submit">Create account</button>
        </form>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
