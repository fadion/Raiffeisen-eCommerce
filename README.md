# Klasa PHP për Payment Gateway të Raiffeisen Bank Albania

Klasa të thjeshta në PHP për të lehtësuar autorizimin dhe proçesimin e pagesës kur përdoret Payment Gateway e shërbimit eCommerce të Raiffeisen Bank Albania. Duhet të keni parasysh se gjenerimi i çertifikatës (që përdoret për autorizim), dërgimi i të dhënave tek Gateway dhe strategjia e ruajtjes së porosisë janë jashtë mundësisë apo qëllimit të këtyre klasave dhe mbeten në dorën tuaj.

## Pse duhet atëherë?

Proçesi i implementimit të pagesës është pak i komplikuar nëse nuk di ç'bën dhe për fat të keq, manualet shoqëruese nuk ndihmojnë sa duhet. Formati i gjenerimit të signature, të dhënat që dërgohen dhe mënyra se si serveri i Gateway i dërgon dhe pret kërkesat, mund të jenë irrituese për programuesit fillestarë.

Këto klasa bëjnë pikërisht punën që një programues s'ka nevojë ta bëjë: gjenerimin e formatit të duhur të signature për autorizim, leximin e kërkesës nga Gateway dhe dërgimin e sinjalit të suksesit apo të kthimit mbrapsht të transkasionit. Gjithçka tjetër mbetet në dorën tuaj.

Krahas kodit, do të lexoni edhe disa këshilla apo praktika të mira për implementimin e sistemit të Raiffeisen.

## Varësitë

Një version i PHP-së më i lartë se 5.3.0 dhe kompilim i PHP-së me [OpenSSL](http://www.php.net/manual/en/openssl.installation.php). Kjo e fundit duhet për shënjimin e çertifikatës.

## Ngarkimi i Klasave

Kjo është strategji që varet nga aplikacioni apo framework-u që po përdorni. Më poshtë keni mënyrat në dispozicion:

### Ngarkim manual

```php
<?php
require_once('src/Raiffeisen/Authenticate.php');
require_once('src/Raiffeisen/Notify.php');
?>
```

### Autoloader

Mund të jetë një implementim i juaji i [spl_autoload_register()](http://www.php.net/manual/en/function.spl-autoload-register.php) apo të ndonjë autoloaderi si [ClassLoader](https://github.com/symfony/ClassLoader) i Symfony. Organizimi i direktorive, namescapes dhe emrat e klasave ndjekin standartin psr-0.

### Composer

[Composer](http://getcomposer.org/) është në fakt mënyra më e lehtë dhe e këshilluar për çdo librari. Praktikisht çdo framework modern për PHP (Zend Framework, Symfony, Laravel, etj) shfrytëzon Composer për të menaxhuar paketat.

Fillimisht, përfshini paketën në composer.json:

```json
"require": { "fadion/raiffeisen": "dev-master" }
```

Instalojeni paketën:

	$ composer install

Fillojeni ta përdorni

```php
<?php
require 'vendor/autoload.php';

$auth = new Raiffeisen\Authenticate(...);
$notify = new Raiffeisen\Notify(...);
?>
```

## Autorizimi

Pjesa e parë është autorizimi që kryhet duke gjeneruar një format të dhënash të varur nga disa variabla dhe nga një çertifikatë. Këtë të fundit do ta gjeneroni në bazë të instruksioneve që do të merrni nga Raiffeisen dhe do ta ruani diku në server.

Nisni klasën e autorizimit duke i kaluar disa parametra. MerchantID dhe TerminalID do ju jepen nga Raiffeisen.

```php
<?php
$merchant_id = '111';
$terminal_id = '222';
$total = 3500;

$auth = new Raiffeisen\Authenticate($merchant_id, $terminal_id, $total);
$data = $auth->generate();
?>
```

Gjithashtu mund të kaloni disa parametra opsionalë nëse doni ti mbivendosni ato të paracaktuarat.

```php
<?php
$options = array(
	'purchase_time' => date('ymdHis', strtotime('-1 hour')), // koha kur eshte kryer porosia
	'order_id' => '11EE5D', // ID e porosise
	'currency_id' => 'usd', // Valuta (all, usd, eur)
	'session_data' => 'abc', // Sesioni
	'cert_dir' => 'cert/dir' // Direktoria ku ndodhet certifikata
);

$auth = new Raiffeisen\Authenticate('111', '222', 3500, $options);
?>
```

ID e porosisë, përveç se si String, mund të kalohet edhe si funksion anonim për ta kryer aty logjikën e gjenerimit:

```php
<?php
$user_id = 10;
$auth = new Raiffeisen\Authenticate('111', '222', 3500, array(
		'order_id' => function() use($user_id)
		{
			return uniqid().$user_id;
		}));
?>
```

Ajo që metoda generate() ju kthen është një Array me të gjitha parametrat që ju duhet për të ndërtuar një formë dhe për ta drejtuar atë forme drejt Gateway. Më poshtë është një shembull që ndërton të dhënat dhe ja kalon një forme.

```php
<?php
$auth = new Raiffeisen\Authenticate('111', '222', 3500);
$data = $auth->generate();
?>
```

```html
<form method="post" action="https://url/e/gateway">
	<input type="hidden" name="Version" value="1">
	<input type="hidden" name="MerchantID" value="<?php echo $data['merchant_id']; ?>">
	<input type="hidden" name="TerminalID" value="<?php echo $data['terminal_id']; ?>">
	<input type="hidden" name="TotalAmount" value="<?php echo $data['total']; ?>">
	<input type="hidden" name="Currency" value="<?php echo $data['currency_id']; ?>">
	<input type="hidden" name="locale" value="sq">
	<input type="hidden" name="PurchaseTime" value="<?php echo $data['purchase_time']; ?>">
	<input type="hidden" name="OrderID" value="<?php echo $data['order_id']; ?>">
	<input type="hidden" name="SD" value="<?php echo $data['session_data']; ?>">
	<input type="hidden" name="Signature" value="<?php echo $data['signature']; ?>">
	<button type="submit">Paguaj</button>
</form>
```

## Proçesimi i Pagesës

Pas autorizimit dhe dërgimit të të dhënave, blerësi mund të fusë kartën e kreditit. Nëse karta është e vlefshme dhe ka fonde, Gateway do i dërgojë një kërkesë serverit tuaj për ta autorizuar pagesën. Ka 2 mënyra për ta pritur atë kërkesë:

1. Përmes browseri-t të blerësit, i cili kthehet tek faqja juaj me disa të dhëna POST. Përveç faktit që përfshihet blerësi në proçesim dhe s'ka pse, pika më negative është se s'ka asnjë mënyrë për ta validuar porosinë dhe për ta anulluar nëse ndodhi ndonjë problem në server.

2. Përmes komunikimit direkt midis serverit të Gateway dhe serverit tuaj, ku dërgohet një kërkesë (me cURL apo çfarëdo) që përmban të dhënat POST tek një adresë e faqes tuaj. Këtu mund të validoni porosinë, ta ruani në databazë, të kryeni çdo veprim që duhet dhe në fund të jepni sinjalin Pozitiv apo Negativ. Vetëm nëse ju ktheni përgjigje pozitive, banka do e proçesojë transaksionin.

Ne do merremi me mënyrën e dytë dhe çdo njeri me pak sens logjik, duhet të ndjekë të njëjtën rrugë. Dokumentimi i Raiffeisen do ju shpjegojë implementimin e të dyja mënyrave.

Duke qenë se kërkesa nga serveri i Gateway dërgohet si POST, edhe klasa merr disa të dhëna përmes saj. Nisja e klasës duhet kryer duke i kaluar 3 parametra: adresa e suksesit (absolute), adresa e dështimit (absolute) dhe superglobalen $_POST. Adresa e suksesit dhe ajo e dështimit janë URL-të ku blerësi do të drejtohet pas proçesimit të pagesës.

```php
<?php
$notify = new Raiffeisen\Notify('http://adresa/suksesit', 'http://adresa/deshtimit', $_POST);
?>
```

Ofrohen disa metoda për të thjeshtësuar përgjigjen.

```php
<?php
$notify = new Raiffeisen\Notify('http://adresa/suksesit', 'http://adresa/deshtimit', $_POST);

// Kontrollon nese kerkesa vjen nga serveri
// i Gateway dhe transaksioni eshte i vlefshem.
$notify->isValid('1.1.1.1');

// Kthen pergjigje pozitive. Transaksioni kryhet.
$notify->success();

// Kthen pergjigje negative. Transaksioni nuk kryhet.
// Mund te kalohet edhe nje arsye opsionale.
$notify->error('Nuk arritem ta ruajme porosine. Provojeni serish.');
?>
```

Sigurisht, se kur kthehet përgjigje pozitive apo negative, kjo s'është përgjegjësi e klasës. Duhet të implementoni një strategji që kontrollon stokun e produktit në momentin e tentativës për blerje, ndryshimet në çmim, etj. Më e rëndësishmja është që tentativa për blerje të anullohet nëse porosia nuk arrihet të ruhet.

Një rend pune tipik do të ishte si në vijim:

```php
<?php
$notify = new Raiffeisen\Notify('http://adresa/suksesit', 'http://adresa/deshtimit', $_POST);

if ($notify->isValid('1.1.1.1'))
{
	// valido porosine, stokun, etj.

	// nese porosia eshte ne rregull.
	if (...)
	{
		echo $notify->success();
	}
	else
	{
		echo $notify->error();
	}
}
?>
```