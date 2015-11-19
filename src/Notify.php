<?php

namespace Fadion\Raiffeisen;

/**
 * Notify
 * 
 * Gjeneron formatin e duhur te pergjigjes qe
 * Gateway i Raiffeisen pret per te konfirmuar
 * ose anuluar pagesen. Klasa supozon se po perdoret
 * NOTIFY_URL e Gateway, qe ben konfirmimin duke
 * komunikuar server me server dhe jo me opsionet
 * me pak te sigurta SUCCESS_URL/FAILURE_URL.
 */
class Notify
{
	/**
	 * @var string URL kur blerja konfirmohet
	 */
	private $successUrl;

	/**
	 * @var string URL kur blerja nuk konfirmohet
	 */
	private $errorUrl;

	/**
	 * @var string Do te mbaje URL-ne e suksesit apo deshtimit
	 */
	private $url;

	/**
	 * @var array Do te mbaje superglobalen $_POST
	 */
	private $post;

	/**
	 * Constructor. Vendos URL-ne e suksesit dhe deshtimit, dhe
	 * vlerat nga superglobalja $_POST
	 * 
	 * @param string $successUrl
	 * @param string $errorUrl
	 * @param array $total Superglobalja $_POST
	 * @return void
	 */
	public function __construct($successUrl, $errorUrl, $postData)
	{
		$this->successUrl = $successUrl;
		$this->errorUrl = $errorUrl;
		$this->post = $postData;
	}

	/**
	 * Kontrollon nese kerkesa nga serveri i Gateway
	 * eshte e vlefshme
	 * 
	 * @param string $ip IP e serverit te Gateway nga pritet kerkesa
	 * @return bool
	 */
	public function isValid($ip)
	{
		// Kodi i Transaksionit (TranCode) duhet te jete '000', qe
		// tregon se transaksioni ne anen e bankes ka qene i suksesshem.
		// Kontrollohet edhe referuesi dhe IP gjithashtu, per te tentuar
		// te shmange kerkesat e nisura manualisht nga dashakeqes
		if ($this->post['TranCode'] == '000' and 
			($_SERVER['HTTP_REFERER'] == $ip or $_SERVER['REMOTE_ADDR'] == $ip)
		) {
			return true;
		}

		return false;
	}

	/**
	 * Vendos nga ana e klientit se porosia u ruajt
	 * dhe duhet te procedohet me pagesen. Metoda duhet
	 * te therritet pasi aplikacioni te kete kryer me
	 * sukses veprimet ne databaze apo cfaredo strategjie
	 * perdoret per te ruajtur blerjet
	 * 
	 * @return string
	 */
	public function success()
	{
		$this->url = $this->successUrl;

		return $this->output('success');
	}

	/**
	 * Vendos nga ana e klientit se porosia nuk u ruajt
	 * dhe nuk duhet te procedohet me pagesen. Ashtu si me
	 * success(), metoda duhet te therritet nese porosia
	 * nuk eshte e vlefshme apo nuk u arriten te kryhen
	 * veprimet ne databaze apo kudo tjeter.
	 * 
	 * @param string $reason Arsyeja pse transaksioni u anullua
	 * @return string
	 */
	public function error($reason = '')
	{
		$this->url = $this->errorUrl;

		return $this->output('reverse', $reason);
	}

	/**
	 * Nderton tekstin ne formatin qe Gateway e kerkon.
	 * 
	 * @param string $action Sukses|Deshtim (Success|Reverse)
	 * @param string $reason Arsyeja pse transaksioni u anullua
	 * @return string
	 */
	private function output($action, $reason = '')
	{
		$output  = "MerchantID=".$this->post['MerchantID']."\n";
		$output .= "TerminalID=".$this->post['TerminalID']."\n";
		$output .= "OrderID=".$this->post['OrderID']."\n";
		$output .= "Currency=".$this->post['Currency']."\n";
		$output .= "TotalAmount=".$this->post['TotalAmount']."\n";
		$output .= "XID=".$this->post['XID']."\n";
		$output .= "PurchaseTime=".$this->post['PurchaseTime']."\n";
		$output .= "Response.action=".$action."\n";
		$output .= "Response.reason=".$reason."\n";
		$output .= "Response.forwardUrl=".$this->url."\n";

		return $output;
	}

}