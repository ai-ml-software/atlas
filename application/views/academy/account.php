<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$account_leads = array(
    'login'=>ha_pt('Sign in to continue your learning.'), 'sign_up'=>ha_pt('Start learning with the academy.'),
    'forgot_password'=>ha_pt('Enter your email to request a password reset link.'),
    'change_password_from_forgot_password'=>ha_pt('Confirm your new password to secure your account.'),
    'two_factor'=>ha_pt('Enter an authenticator code or a recovery code.'),
    'new_login_confirmation'=>ha_pt('Enter the verification code sent to {email}.',array('email'=>(string)$ci->session->userdata('new_device_user_email'))),
    'verification_code'=>ha_pt('Enter the verification code from your email.'));
$account_actions = array('login'=>'login/validate_login','sign_up'=>'login/register',
    'forgot_password'=>'login/forgot_password/frontend',
    'change_password_from_forgot_password'=>'login/change_password/'.rawurlencode($verification_code ?? ''),
    'two_factor'=>'login/two_factor','new_login_confirmation'=>'login/new_login_confirmation/submit',
    'verification_code'=>'login/verify_email_address');
$account_ids = array('login'=>'login-form','sign_up'=>'signup-form','forgot_password'=>'forgot-password',
    'verification_code'=>'email_verification');
$account_url = function($path) use($locale) { return site_url($path).'?lang='.rawurlencode($locale); };
$account_failure = ha_pt('Unable to request a code. Please try again.');
$captcha = in_array($page_name,array('login','sign_up','forgot_password','verification_code','change_password_from_forgot_password'),true);
$captcha_v2 = $captcha && get_frontend_settings('recaptcha_status');
$captcha_v3 = $captcha && !$captcha_v2 && get_frontend_settings('recaptcha_status_v3');
?>
<section class="ha-auth ha-shell" data-account-screen="<?= html_escape($page_name) ?>">
    <aside class="ha-auth__intro">
        <span class="ha-eyebrow"><?= html_escape($ha_brand_name) ?></span>
        <h2><?= html_escape(ha_pt('Your learning, in one place')) ?></h2>
        <p><?= html_escape(ha_pt('Explore courses, build your skills and return to your saved learning.')) ?></p>
        <a class="ha-btn ha-btn--ghost" href="<?= base_url($locale.'/courses') ?>"><?= html_escape(ha_pt('Explore courses')) ?></a>
        <p class="ha-auth__support"><a href="<?= base_url($locale.'/contact') ?>"><?= html_escape(ha_pt('Contact us')) ?></a></p>
    </aside>
    <div class="ha-auth__panel">
        <h1><?= html_escape($account_titles[$page_name]) ?></h1>
        <p class="ha-auth__lede"><?= html_escape($account_leads[$page_name]) ?></p>
        <?php foreach(array('error_message'=>'error','flash_message'=>'success','info_message'=>'info') as $key=>$kind):
            $notice = $ci->session->flashdata($key); if (!$notice) continue; ?>
            <div class="ha-auth__notice ha-auth__notice--<?= $kind ?>" role="<?= $kind==='error' ? 'alert' : 'status' ?>" tabindex="-1" <?= $kind==='error' ? 'data-auth-error' : '' ?>><?= html_escape(ha_pt($notice)) ?></div>
        <?php endforeach; ?>
        <div class="ha-auth__feedback" data-auth-feedback role="status" hidden></div>
        <form class="ha-auth__form" id="<?= $account_ids[$page_name] ?? 'account-form' ?>" action="<?= html_escape($account_url($account_actions[$page_name])) ?>" method="post" <?= $page_name==='sign_up' ? 'enctype="multipart/form-data"' : '' ?> <?= $page_name==='verification_code' ? 'data-auth-verify-email' : '' ?> data-failure="<?= html_escape($account_failure) ?>">
            <?= ha_csrf_field() ?>
            <?php if ($page_name==='sign_up'): ?>
                <div class="ha-auth__names">
                <?php foreach(array('first_name'=>ha_pt('First name'),'last_name'=>ha_pt('Last name')) as $name=>$label): ?>
                    <div class="ha-auth__field"><label for="<?= $name ?>"><?= html_escape($label) ?></label><input id="<?= $name ?>" name="<?= $name ?>" autocomplete="<?= $name==='first_name' ? 'given-name' : 'family-name' ?>" required></div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (in_array($page_name,array('login','sign_up','forgot_password'),true)): ?>
                <div class="ha-auth__field"><label for="email"><?= html_escape(ha_pt('Email address')) ?></label><input id="email" name="email" type="email" dir="ltr" autocomplete="<?= $page_name==='login' ? 'username' : 'email' ?>" value="<?= $page_name==='login' ? html_escape((string)$ci->session->flashdata('ha_login_email')) : '' ?>" required></div>
            <?php endif; ?>
            <?php if (in_array($page_name,array('login','sign_up','change_password_from_forgot_password'),true)):
                $password_fields = $page_name==='change_password_from_forgot_password'
                    ? array('new_password'=>ha_pt('New password'),'confirm_password'=>ha_pt('Confirm your new password'))
                    : array('password'=>ha_pt('Password'));
                foreach($password_fields as $name=>$label): ?>
                <div class="ha-auth__field"><label for="<?= $name ?>"><?= html_escape($label) ?></label>
                    <div class="ha-auth__password">
                        <input id="<?= $name ?>" name="<?= $name ?>" type="password" autocomplete="<?= $page_name==='login' ? 'current-password' : 'new-password' ?>" required <?= $name==='confirm_password' ? 'data-password-confirm data-match-message="'.html_escape(ha_pt('Passwords must match.')).'"' : '' ?>>
                        <button type="button" data-password-toggle="<?= $name ?>" aria-controls="<?= $name ?>" aria-pressed="false" data-show="<?= html_escape(ha_pt('Show password')) ?>" data-hide="<?= html_escape(ha_pt('Hide password')) ?>"><?= html_escape(ha_pt('Show password')) ?></button>
                    </div>
                </div>
                <?php endforeach;
            endif; ?>
            <?php if ($page_name==='login'): ?>
                <a href="<?= html_escape($account_url('login/forgot_password_request')) ?>"><?= html_escape(ha_pt('Forgot password?')) ?></a>
            <?php endif; ?>
            <?php if ($page_name==='two_factor'): ?>
                <div class="ha-auth__field"><label for="ha-2fa-code"><?= html_escape(ha_pt('Authentication code')) ?></label><input id="ha-2fa-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="11" dir="ltr" aria-describedby="account-recovery-help" required><small id="account-recovery-help"><?= html_escape(ha_pt('Lost your phone? Enter one of your recovery codes instead (format xxxxx-xxxxx).')) ?></small></div>
            <?php endif; ?>
            <?php if (in_array($page_name,array('verification_code','new_login_confirmation'),true)):
                $code_name = $page_name==='verification_code' ? 'verification_code' : 'new_device_verification_code';
                $register_email = (string)$ci->session->userdata('register_email'); ?>
                <div class="ha-auth__field"><label for="<?= $code_name ?>"><?= html_escape(ha_pt('Verification code')) ?></label><input id="<?= $code_name ?>" name="<?= $code_name ?>" autocomplete="one-time-code" inputmode="numeric" dir="ltr" required></div>
                <?php if ($page_name==='verification_code'): ?><input type="hidden" name="email" value="<?= html_escape($register_email) ?>"><?php endif; ?>
                <button type="button" class="ha-auth__link" data-auth-resend="<?= html_escape($account_url($page_name==='verification_code' ? 'login/resend_verification_code' : 'login/new_login_confirmation/resend')) ?>" data-email="<?= html_escape($register_email) ?>" data-success="<?= html_escape(ha_pt('A new verification code has been requested.')) ?>" data-failure="<?= html_escape($account_failure) ?>"><?= html_escape(ha_pt('Resend code')) ?></button>
            <?php endif; ?>
            <?php if ($page_name==='sign_up' && get_settings('allow_instructor')):
                $apply_instructor = $ci->input->get('instructor') !== null; ?>
                <label class="ha-auth__check" for="instructor"><input id="instructor" name="instructor" value="yes" type="checkbox" data-instructor-toggle aria-controls="become-instructor-fields" <?= $apply_instructor ? 'checked' : '' ?>><?= html_escape(ha_pt('Apply to become an instructor')) ?></label>
                <fieldset class="ha-auth__instructor" id="become-instructor-fields" <?= $apply_instructor ? '' : 'hidden disabled' ?>>
                    <legend><?= html_escape(ha_pt('Instructor application')) ?></legend>
                    <div class="ha-auth__field"><label for="phone"><?= html_escape(ha_pt('Phone')) ?></label><input id="phone" name="phone" type="tel" autocomplete="tel" required></div>
                    <div class="ha-auth__field"><label for="document"><?= html_escape(ha_pt('Qualification document')) ?></label><input id="document" name="document" type="file" accept=".doc,.docs,.pdf,.txt,.png,.jpg,.jpeg" aria-describedby="account-document-help" required><small id="account-document-help"><?= html_escape(ha_pt('Accepted formats: doc, docs, pdf, txt, png, jpg, jpeg.')) ?></small></div>
                    <div class="ha-auth__field"><label for="message"><?= html_escape(ha_pt('Message')) ?></label><textarea id="message" name="message" rows="4"></textarea></div>
                </fieldset>
            <?php endif; ?>
            <?php if ($captcha_v2): ?><div class="g-recaptcha" data-sitekey="<?= html_escape(get_frontend_settings('recaptcha_sitekey')) ?>"></div><?php endif; ?>
            <button type="submit" class="ha-btn ha-btn--primary ha-auth__submit<?= $captcha_v3 ? ' g-recaptcha' : '' ?>" <?= $captcha_v3 ? 'data-sitekey="'.html_escape(get_frontend_settings('recaptcha_sitekey_v3')).'" data-callback="onAccountSubmit" data-action="submit"' : '' ?>><?php
                if($page_name==='login') echo html_escape(ha_pt('Log in'));
                elseif($page_name==='sign_up') echo html_escape(ha_pt('Sign up'));
                elseif($page_name==='forgot_password') echo html_escape(ha_pt('Send request'));
                elseif($page_name==='two_factor') echo html_escape(ha_pt('Verify'));
                else echo html_escape(ha_pt('Continue'));
            ?></button>
        </form>
        <?php if ($page_name==='login' && in_array(get_settings('public_signup'),array('enable','1',1),true)): ?>
            <p class="ha-auth__switch"><?= html_escape(ha_pt('New to the academy?')) ?> <a href="<?= html_escape($account_url('sign_up')) ?>"><?= html_escape(ha_pt('Create an account')) ?></a></p>
        <?php elseif ($page_name==='sign_up'): ?>
            <p class="ha-auth__switch"><?= html_escape(ha_pt('Already have an account?')) ?> <a href="<?= html_escape($account_url('login')) ?>"><?= html_escape(ha_pt('Log in')) ?></a></p>
        <?php elseif ($page_name!=='login'): ?>
            <p class="ha-auth__switch"><a data-back-login href="<?= html_escape($account_url('login')) ?>"><?= html_escape(ha_pt('Back to login')) ?></a></p>
        <?php endif; ?>
        <?php if (in_array($page_name,array('login','sign_up'),true) && get_settings('fb_social_login')): ?>
            <p class="ha-auth__switch"><?= html_escape(ha_pt('Or')) ?></p>
            <?php include APPPATH.'views/frontend/default-new/facebook_login.php'; ?>
        <?php endif; ?>
    </div>
</section>
<?php if ($captcha_v2 || $captcha_v3): ?><script src="https://www.google.com/recaptcha/api.js" async defer></script><?php endif; ?>
