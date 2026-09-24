<!---------- Header Section start  ---------->
<?php 

validate_cart_items();
$cart_items = $this->session->userdata('cart_items'); 
 
?>
<?php $user_id = $this->session->userdata('user_id'); ?>
<?php $user_login = $this->session->userdata('user_login'); ?>
<?php $admin_login = $this->session->userdata('admin_login'); ?>
<?php if($user_id > 0){$user_details = $this->user_model->get_all_user($user_id)->row_array();} ?>
<header>
  <!-- Sub Header Start -->
  <div class="sub-header">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
          <div class="icon icon-left">
            <!-- The utility bar carries the same line as the public academy
                 chrome: what the academy is, then the two things a visitor
                 arrives looking for. The demo phone and email that shipped
                 with the package are not real contact details, so they are
                 not printed here; the contact page owns them. -->
            <ul class="nav align-items-center">
              <li class="nav-item px-2 d-none d-md-block">
                <span class="text-white-50"><?php echo site_phrase('Hotel training in Arabic and English'); ?></span>
              </li>
              <li class="nav-item px-2">
                <a href="<?php echo base_url('en/verify'); ?>"><?php echo site_phrase('Verify a certificate'); ?></a>
              </li>
              <li class="nav-item px-2">
                <a href="<?php echo base_url('en/contact'); ?>"><?php echo site_phrase('Contact'); ?></a>
              </li>
            </ul>
          </div>
        </div>

        <div class="col-xl-6 col-lg-6 col-md-6 col-sm-12">
          <div class="icon right-icon">
            <?php $facebook = get_frontend_settings('facebook'); ?>
            <?php $twitter = get_frontend_settings('twitter'); ?>
            <?php $linkedin = get_frontend_settings('linkedin'); ?>
            <ul class="nav justify-content-end">
              <?php if($facebook): ?>
                <li class="nav-item">
                  <a target="_blank" href="<?php echo $facebook; ?>"><i class="fa-brands fa-facebook-f"></i></a>
                </li>
              <?php endif; ?>
              <?php if($twitter): ?>
                <li class="nav-item enav-item">
                  <a target="_blank" href="<?php echo $twitter; ?>">
                      <svg width="19" height="19" viewBox="0 0 20 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <g clip-path="url(#clip0_30_3730)">
                          <path d="M11.2841 7.5801L18.2156 0H16.5731L10.5545 6.5817L5.74746 0H0.203125L7.47229 9.95269L0.203125 17.9016H1.84575L8.20153 10.9511L13.2781 17.9016H18.8224L11.2837 7.5801H11.2841ZM9.03434 10.0404L8.29782 9.04931L2.43761 1.16331H4.96059L9.68985 7.52757L10.4264 8.51863L16.5738 16.7912H14.0509L9.03434 10.0408V10.0404Z" fill="#fff"></path>
                          </g>
                          <defs>
                          <clipPath id="clip0_30_3730">
                          <rect width="19.0285" height="17.9016" fill="white"></rect>
                          </clipPath>
                          </defs>
                      </svg>
                  </a>
                </li>
              <?php endif; ?>
              <?php if($linkedin): ?>
                <li class="nav-item">
                  <a target="_blank" href="<?php echo $linkedin; ?>"><i class="fa-brands fa-linkedin"></i></a>
                </li>
              <?php endif; ?>

              <a href="#" class="invisible d-none" onclick="actionTo('<?php echo site_url('home/dark_and_light_mode') ?>')"><i class="fas fa-moon"></i></a>

              <li class="nav-item align-items-center d-flex ms-2">
                <form action="#" method="POST" class="language-control select-box">
                  <select onchange="actionTo(`<?php echo site_url('home/switch_language/') ?>${$(this).val()}`)" class="select-control form-select nice-select">
                    <?php
                    $languages = $this->crud_model->get_all_languages();
                    $selected_language = $this->session->userdata('language');
                    foreach ($languages as $language): ?>
                      <?php if (trim($language) != ""): ?>
                        <option value="<?php echo strtolower($language); ?>" <?php if ($selected_language == $language): ?>selected<?php endif; ?>><?php echo ucwords($language);?></option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </select>
                </form>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!---- Sub Header End ------>
  
  <section class="menubar">
    <?php include "header_lg_device.php"; ?>
    <!-- Offcanves Menu  -->
    <?php include "header_sm_device.php"; ?>
  </section>
</header>
<!---------- Header Section End  ---------->