<?php
require(dirname(__FILE__) . '/wp-load.php');

$redirect = false;


if(is_user_logged_in()){
	$secret_salt = SSO_SALT;
	$timestamp = time();
	$newuser = 1;
  $current_user = wp_get_current_user();
  $user=$current_user->user_login;
  $email=$current_user->user_email;
  $fn = get_user_meta($current_user->ID, 'billing_first_name', true);
  $ln = get_user_meta($current_user->ID, 'billing_last_name', true);
  $city = get_user_meta($current_user->ID, 'billing_city', true);
  $country = get_user_meta($current_user->ID , 'billing_country', true);
  $company = get_user_meta($current_user->ID , 'billing_company', true);

  if(empty($city)) $city = SSO_CITY;

  if(empty($country)) $country = SSO_COUNTRY;

  $products = matc_get_all_products_ordered_by_user($current_user->ID);

  if(count($products) > 0 && is_array($products)) {
    $cohorts = implode(',', $products);
    $redirect = true;

    $token = crypt($timestamp.$user,$secret_salt);
    $url = SSO_URL;
    $sso_url = $url.'?user='.$user.
                    '&token='.$token.
                    '&ts='.$timestamp.
                    '&email='.$email.
                    '&newuser='.$newuser.
                    '&fn='.$fn.
                    '&ln='.$ln.
                    '&city='.$city.
                    '&country='.$country.
                    '&company='.$company.
                    '&cohorts='.$cohorts;
  }
}

echo "<pre>";
 var_dump($sso_url);
echo "</pre>";
exit;
if($redirect && isset($sso_url)) {
	header("Location: ".$sso_url);
} else {
 	$loginurl = get_bloginfo('url') . "/my-account/?redirect=0";
 	header("Location:".$loginurl);
}

