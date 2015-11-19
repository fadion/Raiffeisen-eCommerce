<?php
namespace Fadion\Raiffeisen;

/**
 * Authenticate
 * 
 * Gjeneron formatin e duhur te "signature" qe
 * Gateway i Raiffeisen kerkon per te autorizuar
 * kerkesen. Kthen nje array me te dhenat per tu
 * perdorur ne formen e kerkeses.
 *
 * @package Raiffeisen\Authenticate
 * @author Fadion Dashi
 * @version 1.1.0
 */
class Authenticate
{

	/**
	 * @var string ID e tregtarit. Jepet nga banka.
	 */
	private $merchant_id;

	/**
	 * @var string ID e terminalit. Jepet nga banka.
	 */
	private $terminal_id;

	/**
	 * @var mixed Totali i parave qe duhen paguar.
	 */
	private $total_amount;

	/**
	 * @var string Koha e blerjes.
	 */
	private $purchase_time;

	/**
	 * @var string ID e porosise.
	 */
	private $order_id;

	/**
	 * @var string ID e valutes. Paravendosur ne "LEK".
	 */
	private $currency_id = '008';

	/**
	 * @var string ID e valutes. Paravendosur ne "LEK".
	 */
	private $session_data = '';

	/**
	 * @var string Direktoria ku ndodhet certifikata.
	 */
	private $cert_dir = 'cert';

	/**
	 * @var string Kodet e valutes.
	 */
	private $currency_codes = array(
		'all' => '008',
		'usd' => '840',
		'eur' => '978'
	);

	/**
	 * Constructor. Vendos te dhenat qe kerkohen per te gjeneruar
	 * nje signature.
	 * 
	 * @param string $merchant_id
	 * @param string $terminal_id
	 * @param mixed $total_amount Totali i parave qe duhen paguar
	 * @param array $options Opsione ekstra: 'purchase_time', 'order_id', 'currency_id', 'cert_dir'
	 * @return void
	 */
	public function __construct($merchant_id, $terminal_id, $total_amount, $options = array())
	{
		$this->merchant_id = $merchant_id;
		$this->terminal_id = $terminal_id;
		$this->total_amount = $total_amount;

		$this->readOptions($options);
		$this->defaultOptions();
	}

	/**
	 * Factory per ta gjeneruar klasen pa nevojen
	 * e therritjes se operatorit "new".
	 * 
	 * @param string $merchant_id
	 * @param string $terminal_id
	 * @param mixed $total_amount
	 * @param array $options
	 * @return Authenticate
	 */
	public static function factory($merchant_id, $terminal_id, $total_amount, $options = array())
	{
		return new self($merchant_id, $terminal_id, $total_amount, $options = array());
	}

	/**
	 * Vendos opsionet e percaktuara nga klienti.
	 * 
	 * @return void
	 */
	private function readOptions($options)
	{
		// Nese s'ka opsione, mos vazhdo.
		if (! count($options)) return;

		if (isset($options['purchase_time'])) $this->purchase_time = $options['purchase_time'];

		if (isset($options['order_id']))
		{
			// ID e porosise mund te kalohet edhe si funksion
			// anonim. is_callable() kontrollon nese eshte i tille.
			if (is_callable($options['order_id']))
			{
				// Vendos vleren e order_id me ate qe kthen funksioni.
				$this->order_id = call_user_func($options['order_id']);
			}
			else
			{
				$this->order_id = $options['order_id'];
			}
		}
		
		// Valuta duhet te jete vendosur dhe te egzistoje tek
		// lista e valutave te pranuara.
		if (isset($options['currency_id']) and isset($this->currency_codes[$options['currency_id']]))
		{
			$this->currency_id = $this->currency_codes[$options['currency_id']];
		}

		if (isset($options['session_data'])) $this->session_data = $options['session_data'];
		if (isset($options['cert_dir'])) $this->cert_dir = rtrim($options['cert_dir'], '/\\');
	}

	/**
	 * Vendos disa opsione baze nese nuk jane percaktuar
	 * me pare nga klienti.
	 * 
	 * @return void
	 */
	private function defaultOptions()
	{
		// 'ymdHis' eshte formati i kohes qe banka
		// e kerkon.
		if (! $this->purchase_time) $this->purchase_time = date('ymdHis');

		// Gjenero nje ID porosie.
		if (! $this->order_id) $this->order_id = uniqid();
	}

	/**
	 * Kthen te dhenat si array per tu perdorur me pas
	 * ne formen e kerkeses.
	 * 
	 * @return array
	 */
	public function generate()
	{
		$signature = $this->generateSignature();

		return array(
			'merchant_id' => $this->merchant_id,
			'terminal_id' => $this->terminal_id,
			'total_amount' => $this->total_amount,
			'currency_id' => $this->currency_id,
			'purchase_time' => $this->purchase_time,
			'order_id' => $this->order_id,
			'session_data' => $this->session_data,
			'signature' => $signature
		);
	}

	/**
	 * Gjeneron signature.
	 * 
	 * @return string
	 */
	private function generateSignature()
	{
		// Formati i te dhenave ne baze te manualit.
		$data = $this->formatData();

		// Lexo certifikaten dhe shenjoje me OpenSSL.
		$key = file_get_contents("$this->cert_dir/$this->merchant_id.pem");
		$key = openssl_get_privatekey($key);
		openssl_sign($data, $signature, $key);
		openssl_free_key($key);

		return $signature = base64_encode($signature);
	}

	/**
	 * Formaton te dhenat per tu perdorur ne
	 * gjenerimin e signature.
	 * 
	 * @return string
	 */
	public function formatData()
	{
		return "$this->merchant_id;$this->terminal_id;$this->purchase_time;$this->order_id;$this->currency_id;$this->total_amount;$this->session_data;";
	}

}