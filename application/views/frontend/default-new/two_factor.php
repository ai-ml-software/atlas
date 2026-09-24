<section class="sign-up my-5 pt-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 col-sm-12 col-12">
                <div class="sing-up-right">
                    <h3><?php echo get_phrase('Two-factor login'); ?><span>.</span></h3>
                    <p><?php echo get_phrase('Open your authenticator app and enter the 6-digit code for Hospitality Academy.'); ?></p>

                    <form action="<?php echo site_url('login/two_factor'); ?>" method="post" autocomplete="off">
                        <?php echo ha_csrf_field(); ?>
                        <div class="mb-3">
                            <label for="ha-2fa-code" class="h5 d-block"><?php echo get_phrase('Authentication code'); ?></label>
                            <div class="position-relative">
                                <i class="fa-solid fa-shield-halved"></i>
                                <input type="text" class="form-control" id="ha-2fa-code" name="code"
                                       inputmode="numeric" autocomplete="one-time-code" maxlength="11"
                                       placeholder="123 456" required autofocus>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <?php echo get_phrase('Lost your phone? Enter one of your recovery codes instead (format xxxxx-xxxxx).'); ?>
                            </small>
                        </div>
                        <div class="log-in">
                            <button type="submit" class="btn btn-primary"><?php echo get_phrase('Verify'); ?></button>
                        </div>
                    </form>

                    <div class="log-in">
                        <a href="<?php echo site_url('login'); ?>" class="btn btn-primary my-0">
                            <span class="fas fa-angle-left"></span> <?php echo get_phrase('Back to login'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
