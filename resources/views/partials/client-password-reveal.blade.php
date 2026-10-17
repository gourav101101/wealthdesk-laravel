<dialog id="client-password-reveal" class="native-dialog" aria-labelledby="client-password-title">
    <form method="post" data-client-password-form>
        @csrf
        <h2 id="client-password-title">View client password</h2>
        <p data-password-client-name></p>
        <div data-password-confirmation>
            <p>Confirm your administrator password to view this client’s current password. Each view is recorded in the activity log.</p>
            <div class="field login-password-field"><label for="reveal-admin-password">Your admin password</label><input id="reveal-admin-password" name="current_password" type="password" required maxlength="128" autocomplete="current-password"><button type="button" class="login-password-toggle" data-admin-password-toggle aria-controls="reveal-admin-password" aria-label="Show password" aria-pressed="false" hidden>Show</button></div>
        </div>
        <div class="error-box" data-password-error role="alert" hidden></div>
        <p data-password-reset hidden><a class="btn primary" data-password-reset-link>Edit client / reset password</a></p>
        <div data-password-result hidden>
            <label class="field"><span>Current client password</span><input type="text" data-client-password-value readonly autocomplete="off" spellcheck="false"></label>
            <p class="password-reveal-note">This password will be hidden automatically after 30 seconds.</p>
        </div>
        <div class="modal-actions"><button class="btn secondary" type="button" data-close-dialog>Close</button><button class="btn primary" type="submit" data-password-reveal-submit><x-icon name="eye" :size="16"/>Reveal password</button></div>
    </form>
</dialog>
