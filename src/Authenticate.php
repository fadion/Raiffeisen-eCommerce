<?php

namespace Fadion\Raiffeisen;

/**
 * Authenticate
 * 
 * Gjeneron formatin e duhur te "signature" qe
 * Gateway i Raiffeisen kerkon per te autorizuar
 * kerkesen. Kthen nje array me te dhenat per tu
 * perdorur ne formen e kerkeses.
 */
class Authenticate
{
	/**
	 * @var string ID e tregtarit. Jepet nga banka
	 */
	private $merchantId;

	/**
	 * @var string ID e terminalit. Jepet nga banka
	 */
	private $terminalId;

	/**
	 * @var mixed Totali i parave qe duhen paguar
	 */
	private $totalAmount;

	/**
	 * @var string Koha e blerjes
	 */
	private $purchaseTime;

	/**
	 * @var string ID e porosise
	 */
	private $orderId;

	/**
	 * @var string ID e valutes. Paravendosur ne "LEK"
	 */
	private $currencyId = '008';

	/**
	 * @var string ID e valutes. Paravendosur ne "LEK"
	 */
	private $sessionData = '';

	/**
	 * @var string Direktoria ku ndodhet certifikata
	 */
	private $certDir = 'cert';

	/**
	 * @var string Kodet e valutes
	 */
	private $currencyCodes = [
		'all' => '008',
		'usd' => '840',
		'eur' => '978'
	];

	/**
	 * Constructor. Vendos te dhenat qe kerkohen per te gjeneruar
	 * nje signature
	 * 
	 * @param string $merchantId
	 * @param string $terminalId
	 * @param mixed $totalAmount Totali i parave qe duhen paguar
	 * @param array $options Opsione ekstra: 'purchase_time', 'order_id', 'currency_id', 'cert_dir'
	 * @return void
	 */
	public function __construct($merchantId, $terminalId, $totalAmount, array $options = [])
	{
		$this->merchantId = $merchantId;
		$this->terminalId = $terminalId;
		$this->totalAmount = $totalAmount;

		$this->readOptions($options);
		$this->defaultOptions();
	}

	/**
	 * Vendos opsionet e percaktuara nga klienti
	 * 
	 * @return void
	 */
	private function readOptions($options)
	{
		// Nese s'ka opsione, mos vazhdo.
		if (!count($options)) return;

		if (isset($options['purchase_time'])) {
			$this->purchaseTime = $options['purchase_time'];
		}

		if (isset($options['order_id'])) {
			// ID e porosise mund te kalohet edhe si funksion
			// anonim. is_callable() kontrollon nese eshte i tille
			if (is_callable($options['order_id'])) {
				// Vendos vleren e orderId me ate qe kthen funksioni
				$this->orderId = call_user_func($options['order_id']);
			}
			else {
				$this->orderId = $options['order_id'];
			}
		}
		
		// Valuta duhet te jete vendosur dhe te egzistoje tek
		// lista e valutave te pranuara
		if (isset($options['currency_id']) and isset($this->currencyCodes[$options['currency_id']])) {
			$this->currencyId = $this->currencyCodes[$options['currency_id']];
		}

		if (isset($options['session_data'])) $this->sessionData = $options['session_data'];
		if (isset($options['cert_dir'])) $this->certDir = rtrim($options['cert_dir'], '/\\');
	}

	/**
	 * Vendos disa opsione baze nese nuk jane percaktuar
	 * me pare nga klienti
	 * 
	 * @return void
	 */
	private function defaultOptions()
	{
		// 'ymdHis' eshte formati i kohes qe kerkon banka
		if (! $this->purchaseTime) {
			$this->purchaseTime = date('ymdHis');
		}

		// Gjenero nje ID porosie
		if (! $this->orderId) {
			$this->orderId = uniqid();
		}
	}

	/**
	 * Kthen te dhenat si array per tu perdorur me pas
	 * ne formen e kerkeses
	 * 
	 * @return array
	 */
	public function generate()
	{
		$signature = $this->generateSignature();

		return [
			'merchant_id' => $this->merchantId,
			'terminal_id' => $this->terminalId,
			'total_amount' => $this->totalAmount,
			'currency_id' => $this->currencyId,
			'purchase_time' => $this->purchaseTime,
			'order_id' => $this->orderId,
			'session_data' => $this->sessionData,
			'signature' => $signature
		];
	}

	/**
	 * Gjeneron signature
	 * 
	 * @return string
	 */
	private function generateSignature()
	{
		// Formati i te dhenave ne baze te manualit
		$data = $this->formatData();

		// Lexo certifikaten dhe shenjoje me OpenSSL
		$key = file_get_contents("$this->certDir/$this->merchantId.pem");
		$key = openssl_get_privatekey($key);
		openssl_sign($data, $signature, $key);
		openssl_free_key($key);

		return $signature = base64_encode($signature);
	}

	/**
	 * Formaton te dhenat per tu perdorur ne
	 * gjenerimin e signature
	 * 
	 * @return string
	 */
	public function formatData()
	{
		return "$this->merchantId;$this->terminalId;$this->purchaseTime;$this->orderId;$this->currencyId;$this->totalAmount;$this->sessionData;";
	}

}