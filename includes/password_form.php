<?php
declare(strict_types=1);
?>
<form method="post" class="form-card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <h2>Password</h2>
    <?php if (!empty($user['must_change_password'])): ?>
        <p class="text-danger">Change the starting password before using the rest of the marketplace.</p>
    <?php endif; ?>
    <div class="mb-3">
        <label class="form-label" for="current_password">Current password</label>
        <input class="form-control" id="current_password" name="current_password" type="password" required maxlength="72">
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">New password</label>
        <input class="form-control" id="password" name="password" type="password" required minlength="8" maxlength="72" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,72}">
    </div>
    <div class="mb-3">
        <label class="form-label" for="password_confirmation">Confirm new password</label>
        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required maxlength="72">
    </div>
    <button class="btn btn-accent" type="submit">Update password</button>
</form>
