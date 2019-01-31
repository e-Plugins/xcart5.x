<?php
/* vim: set ts=4 sw=4 sts=4 et: */
/*****************************************************************************\
 +-----------------------------------------------------------------------------+
| X-Cart Software license agreement                                           |
| Copyright (c) 2001-2016 Qualiteam software Ltd <info@x-cart.com>            |
| All rights reserved.                                                        |
+-----------------------------------------------------------------------------+
| PLEASE READ  THE FULL TEXT OF SOFTWARE LICENSE AGREEMENT IN THE "COPYRIGHT" |
| FILE PROVIDED WITH THIS DISTRIBUTION. THE AGREEMENT TEXT IS ALSO AVAILABLE  |
| AT THE FOLLOWING URL: http://www.x-cart.com/license.php                     |
|                                                                             |
| THIS AGREEMENT EXPRESSES THE TERMS AND CONDITIONS ON WHICH YOU MAY USE THIS |
| SOFTWARE PROGRAM AND ASSOCIATED DOCUMENTATION THAT QUALITEAM SOFTWARE LTD   |
| (hereinafter referred to as "THE AUTHOR") OF REPUBLIC OF CYPRUS IS          |
| FURNISHING OR MAKING AVAILABLE TO YOU WITH THIS AGREEMENT (COLLECTIVELY,    |
    | THE "SOFTWARE"). PLEASE REVIEW THE FOLLOWING TERMS AND CONDITIONS OF THIS   |
| LICENSE AGREEMENT CAREFULLY BEFORE INSTALLING OR USING THE SOFTWARE. BY     |
| INSTALLING, COPYING OR OTHERWISE USING THE SOFTWARE, YOU AND YOUR COMPANY   |
| (COLLECTIVELY, "YOU") ARE ACCEPTING AND AGREEING TO THE TERMS OF THIS       |
| LICENSE AGREEMENT. IF YOU ARE NOT WILLING TO BE BOUND BY THIS AGREEMENT, DO |
| NOT INSTALL OR USE THE SOFTWARE. VARIOUS COPYRIGHTS AND OTHER INTELLECTUAL  |
| PROPERTY RIGHTS PROTECT THE SOFTWARE. THIS AGREEMENT IS A LICENSE AGREEMENT |
| THAT GIVES YOU LIMITED RIGHTS TO USE THE SOFTWARE AND NOT AN AGREEMENT FOR  |
| SALE OR FOR TRANSFER OF TITLE. THE AUTHOR RETAINS ALL RIGHTS NOT EXPRESSLY  |
| GRANTED BY THIS AGREEMENT.                                                  |
+-----------------------------------------------------------------------------+
\*****************************************************************************/

/**
 * Error message page interface
*
* @category   X-Cart
* @package    X-Cart
* @subpackage Customer interface
* @author     Ruslan R. Fazlyev <rrf@x-cart.com>
* @copyright  Copyright (c) 2001-2016 Qualiteam software Ltd <info@x-cart.com>
* @license    http://www.x-cart.com/license.php X-Cart license agreement
* @version    3f68edb4b06a5e8ffa772f74caab24df25f6e212, v34 (xcart_4_7_5), 2016-02-18 13:44:28, error_message.php, aim
* @link       http://www.x-cart.com/
* @see        ____file_see____
*/

require __DIR__.'/auth.php';

include $xcart_dir . '/include/common.php';


if ( !defined('XCART_SESSION_START') ) { header("Location: ../"); die("Access denied"); }

$template_main['bankwire_main'] = 'customer/bankwire_success.tpl';
$smarty->assign('main', 'bankwire_main');

$trxid = isset($_GET['trxid']) ? $_GET['trxid'] : "";
if(empty($trxid)){
    func_header_location($current_location . DIR_CUSTOMER . "/cart.php?mode=checkout");
    exit();
}
$success_text_en = <<<HTML
<div class="bankwire-info">
    <h4>Thank you for ordering in our webshop!</h4>
    <p>
        You will receive your order as soon as we receive payment from the bank. <br>
        Would you be so friendly to transfer the total amount of {{amount}}  to the bankaccount <b>
        {{bic}} </b> in name of {{beneficiary}}* ?
    </p>
    <p>
        State the payment feature <b>{{trxid}}</b>, this way the payment can be automatically processed.<br>
        As soon as this happens you shall receive a confirmation mail on {{email}}.
    </p>
    <p>
        If it is necessary for payments abroad, then the BIC code from the bank {{iban}} and the name of the bank is {{bank}}.
    <p>
        <i>* Payment for our webstore is processed by TargetMedia. TargetMedia is certified as a Collecting Payment Service Provider by Currence. This means we set the highest security standards when is comes to security of payment for you as a customer and us as a webshop.</i>
    </p>
</div>
HTML;

$success_text_nl = <<<HTML
<h2>Bedankt voor uw bestelling in onze webwinkel!</h2>
<div class="bankwire-info">
    <p>
        U ontvangt uw bestelling zodra we de betaling per bank ontvangen hebben. <br>
        Zou u zo vriendelijk willen zijn het totaalbedrag van {{amount}} over te maken op bankrekening <b>
		{{bic}} </b> t.n.v. {{beneficiary}}* ?
    </p>
    <p>
        Vermeld daarbij als betaalkenmerk <b>{{trxid}}</b>, zodat de betaling automatisch verwerkt kan worden.
        Zodra dit gebeurd is ontvangt u een mail op {{email}} ter bevestiging.
    </p>
    <p>
        Mocht het nodig zijn voor betalingen vanuit het buitenland, dan is de BIC code van de bank {{iban}} en de naam van de bank is '{{bank}}'.
        Zorg ervoor dat u kiest voor kosten in het buitenland voor eigen rekening (optie: OUR), anders zal het bedrag wat binnenkomt te laag zijn.
    <p>
        <i>* De betalingen voor onze webwinkel worden verwerkt door TargetMedia. TargetMedia is gecertificeerd als Collecting Payment Service Provider door Currence.
        Dat houdt in dat zij aan strenge eisen dient te voldoen als het gaat om de veiligheid van de betalingen voor jou als klant en ons als webwinkel.</i>
    </p>
</div>
HTML;

$check_language = "SELECT * FROM $sql_tbl[languages] WHERE code = 'en' and name = 'targetpay_bankwire_success_text' limit 1";
if(mysql_num_rows(db_query($check_language)) != 1){
    db_query("INSERT INTO $sql_tbl[languages] VALUES ('en','targetpay_bankwire_success_text',' ". addslashes($success_text_en) . "','Text')");
}
$check_language = "SELECT * FROM $sql_tbl[languages] WHERE code = 'nl' and name = 'targetpay_bankwire_success_text' limit 1";
if(mysql_num_rows(db_query($check_language)) != 1){
    db_query("INSERT INTO $sql_tbl[languages] VALUES ('nl','targetpay_bankwire_success_text',' ". addslashes($success_text_nl) . "','Text')");
}
$template_content = $success_text_en;
// Check default customer site language
$check_language = "SELECT * FROM $sql_tbl[config] WHERE name='default_customer_language'";
$result_lng = db_query($check_language);
if(mysql_num_rows($result_lng) == 1){
    $result_lng = mysql_fetch_object($result_lng);
    if($result_lng->value == "nl"){
        $template_content = $success_text_nl;
    }
}

$sql = "SELECT * FROM `digiwallet_transactions` WHERE `digi_txid` = '" . $trxid . "' ORDER BY id DESC LIMIT 1";
$result = db_query($sql);
if (mysql_num_rows($result) != 1) {
    func_header_location($current_location . DIR_CUSTOMER . "/error_message.php?error=error_ccprocessor_error&bill_message=Error, no entry found with transaction id: " . htmlspecialchars($trxid));
    exit();
}
$sale_result = mysql_fetch_object($result);
list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $sale_result->more);
$userinfo = func_userinfo(0, $login_type, false, false, 'H');
// Get order information
$sql = "SELECT * FROM $sql_tbl[orders] WHERE `orderid` = '" . $sale_result->order_id . "'";
$result = db_query($sql);
if (mysql_num_rows($result) != 1) {
    func_header_location($current_location . DIR_CUSTOMER . "/error_message.php?error=error_ccprocessor_error&bill_message=Error, no entry found with order id: " . htmlspecialchars($sale_result->order_id));
    exit();
}
$order_result = mysql_fetch_object($result);
// Encode email address
$emails = str_split($userinfo['email']);
$counter = 0;
$cus_email = "";
foreach ($emails as $char) {
  if($counter == 0) {
      $cus_email .= $char;
      $counter++;
  } else if($char == "@") {
      $cus_email .= $char;
      $counter++;
  } else if($char == "." && $counter > 1) {
      $cus_email .= $char;
      $counter++;
  } else if($counter > 2) {
      $cus_email .= $char;
  } else {
      $cus_email .= "*";
  }
}
$smarty->assign('data', [
    'trxid' => $trxid,
    'account_number' => $accountNumber,
    'iban' => $iban,
    'bic' => $bic,
    'beneficiary' => $beneficiary,
    'bank' => $bank,
    'email' => $cus_email,
    'amount' => $order_result->total,
    'template_content' => $template_content
]);


/**
 * Assign login information
 */
x_session_register('login_antibot_on');
x_session_register('antibot_err');
x_session_register('username');

$smarty->assign('username', stripslashes($username));
$smarty->assign('login_antibot_on', $login_antibot_on);


func_display('customer/home.tpl', $smarty);
?>
