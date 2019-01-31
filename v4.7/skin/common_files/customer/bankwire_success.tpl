{*
850e5138e855497e58a9e99e00c2e8e04e3f7212, v1 (xcart_4_4_0_beta_2), 2010-05-21 08:31:50, bankwire_success.tpl, joy
vim: set ts=2 sw=2 sts=2 et:
*}
{assign var="template" value=$data['template_content']}
{if !empty($lng.targetpay_bankwire_success_text)} 
	{assign var="template" value=$lng.targetpay_bankwire_success_text}
{/if}
{$template|substitute:"amount":{currency value=$data['amount']}:"bic":$data['bic']:"beneficiary":$data['beneficiary']:"trxid":$data['trxid']:"email":$data['email']:"iban":$data['iban']:"bank":$data['bank']}
