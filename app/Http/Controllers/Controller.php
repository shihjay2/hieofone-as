<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;

use Config;
use DB;
use Google_Client;
use Mail;
use Swift_Mailer;
use Swift_SmtpTransport;

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;

	protected function gen_uuid()
	{
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	protected function gen_secret()
	{
		$length = 512;
		$val = '';
		for ($i = 0; $i < $length; $i++) {
			$val .= rand(0,9);
		}
		$fp = fopen('/dev/urandom','rb');
		$val = fread($fp, 32);
		fclose($fp);
		$val .= uniqid(mt_rand(), true);
		$hash = hash('sha512', $val, true);
		$result = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
		return $result;
	}

	protected function base64url_encode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	} 

	protected function send_mail($template, $data_message, $subject, $to)
	{
		$google_client = DB::table('oauth_rp')->where('type', '=', 'google')->first();
		$google = new Google_Client();
		$google->setClientID($google_client->client_id);
		$google->setClientSecret($google_client->client_secret);
		$google->refreshToken($google_client->refresh_token);
		$credentials = $google->getAccessToken();
		$username = $google_client->smtp_username;
		$password = $credentials['access_token'];
		$config = [
			'mail.driver' => 'smtp',
			'mail.host' => 'smtp.gmail.com',
			'mail.port' => 465,
			'mail.from' => ['address' => null, 'name' => null],
			'mail.encryption' => 'ssl',
			'mail.username' => $username,
			'mail.password' => $password,
			'mail.sendmail' => '/usr/sbin/sendmail -bs'
		];
		config($config);
		extract(Config::get('mail'));
		$transport = Swift_SmtpTransport::newInstance($host, $port, 'ssl');
		$transport->setAuthMode('XOAUTH2');
		if (isset($encryption)) $transport->setEncryption($encryption);
		if (isset($username)) {
			$transport->setUsername($username);
			$transport->setPassword($password);
		}
		$owner = DB::table('owner')->first();
		Mail::setSwiftMailer(new Swift_Mailer($transport));
		Mail::send($template, $data_message, function($message) use ($to, $subject, $owner) {
			$message->to($to)
				->from($owner->email, $owner->firstname . ' ' . $owner->lastname)
				->subject($subject);
		});
		return "E-mail sent.";
	}

	/**
	* SMS notifcation with TextBelt
	*
	* @return Response
	*/
	protected function textbelt($number, $message)
	{
		$url = 'http://textbelt.com/text';
		$message = http_build_query([
			'number' => $number,
			'message' => $message
		]);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec ($ch);
		curl_close ($ch);
		return $output;
	}
}
