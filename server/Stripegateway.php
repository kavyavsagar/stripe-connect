<?php
// Codeigniter access check, remove it for direct use
if( !defined( 'BASEPATH' ) ) exit( 'No direct script access allowed' );
 
require_once('stripe-php/init.php');

// Set the server api endpoint and http methods as constants
define( 'STRIPE_API_ENDPOINT', 'https://api.stripe.com/v1/' );

define('TOKEN_URI', 'https://connect.stripe.com/oauth/token/');


/**
 * A simple to use library to access the stripe.com services
 * 
 */
class Stripegateway {
	/**
	 * Holder for the initial configuration parameters 
	 * 
	 * @var     resource
	 * @access  private
	 */
	private $_conf = NULL;
	public $secret_key = NULL;
	public $client_id = NULL;

	
	/**
	 * Constructor method
	 * 
	 * @param  array         Configuration parameters for the library
	 */
	public function __construct(  ) {
		// Store the config values		

		// Configuration options
		$this->_conf['stripe_key_test_public']         = 'pk_test_x6UTPP7RIQfabppwq221znK3';
		$this->_conf['stripe_key_test_secret']         = 'sk_test_Woxsi5xbFTEm3XbjcMPmUcww';
		$this->_conf['stripe_key_live_public']         = '';
		$this->_conf['stripe_key_live_secret']         = '';

		$this->_conf['stripe_test_mode']               = TRUE;
		$this->_conf['stripe_verify_ssl']              = FALSE;

		$this->_conf['client_id_test']				   = 'ca_ECBf4knotIamplJJ9xuWYK6ISS0jzOgy';
		$this->_conf['client_id_live']				   = '';

		$this->_conf['redirect_uri']				   = 'https://example.com/api/user/stripeconnected';
		
		if( $this->_conf['stripe_test_mode'] ){
			$this->secret_key = $this->_conf['stripe_key_test_secret'];
			$this->client_id = $this->_conf['client_id_test'];
		}
		else{
			$this->secret_key = $this->_conf['stripe_key_live_secret'];	
			$this->client_id = $this->_conf['client_id_live'];	
		}		

		\Stripe\Stripe::setApiKey($this->secret_key);
	}
	
	/**
	 * Payment and apply a fee to the buyer based on it's stripe_id
	 * 
	 * @param  array     The amount to charge in cents ( USD ) 
	 */
	public function checkout( $data ) {

		if(empty($data)){
			return array("error" => true, "message" => "Missing Parameters");
		}	   
	   
	    try{
	    	// convert dollar to cent
 			$tmp = str_replace('$', '', $data["amount"]);
    		$amt = number_format((float)$tmp*100., 0, '.', '');

    	    // cal the 10% of amount. 10/100 * amount
    	    $fee = ( $amt - (0.1 * $amt) ); 	    
 
     		$charge = \Stripe\Charge::create([
    	            "amount" => $amt, // cent 
                    "currency" => "usd", 
                    "source" => $data["source"],
                    "customer" => $data["customer_id"],                       
                    "destination" => [
			                    	"amount" => $fee,
			    					"account" => $data["stripe_account_id"]
				    				]
    				]);

			return array("error" => false, "message" => "Sucessfully Charged ".$charge->status, "result" =>$charge);			
                  
	    }catch(Exception $e){ 
	    	return array("error" => true, "message" => $e->getMessage());
	    }
	    
	}
	/*
	* https://stripe.com/docs/connect/deferred-standard-accounts
	* 
	* Stripe account of seller is now connected to your platform
	*/
	public function connectStripeAccount($data){

		if(!$data["country"] || !$data["email"]){
			return array("error" => true, "message" => "Missing Parameters");
		}

		try{			
			$account =	\Stripe\Account::create([
				"country" => $data["country"],
				"type" => "standard",
				"email" => $data["email"]
			]);			
			
			return array("error" => false, "message" => "Sucessfully created account", "result" =>$account);

		}catch(Exception $e){	
			$er = json_decode($e->gethttpBody());
			if($er->error->code == 'account_already_exists'){

				// if the user already have stripe account, send mail to them with connect button
				$emailSend = $this->connectExistingAccount($data);

				return $emailSend;

			}else{
				return array("error" => true, "message" => $e->getMessage());	
			}	    	
		}	

	}

	/*
	* Create customer account for buyer
	*/
	public function createStripeCustomer($data){

		if(empty($data)){
			return array("error" => true, "message" => "Missing Parameters");
		}

		try{
			// Create a Customer:
			$customer = \Stripe\Customer::create([
			    'source' => $data["stripe_id"],
			    'email' => $data["email"]
			]);

			return array("error" => false, "message" => "Sucessfully created Customer", "result" =>$customer);

		}catch(Exception $e){
			return array("error" => true, "message" => $e->getMessage());
		}		
	}

	// send mail with connect button for existing strip user
	public function connectExistingAccount($data){

		if(empty($data)){
			return array("error" => true, "message" => "Missing mail Parameters");
		}

		try{
			
			// to make url safe encode
			$qrystring = strtr(base64_encode('{"uid": '.$data["userid"].'}'), '+/=', '-_,');

			$emailto = $data["email"];
			$toname = strstr($emailto, '@', true);
			$emailfrom = 'no-reply@studionow247.com';
			$fromname = 'Admin';
			$subject = 'Connect with Stripe Account';		

			$message = '<html><body>';		
			$message .= '<table rules="all" style="border-color: #666;" cellpadding="10">';
			$message .= "<tr style='background: #eee;'><td><strong>Connect your stripe account</strong> </td><td>https://connect.stripe.com/oauth/authorize?response_type=code&client_id=" . $this->client_id . "&scope=read_write&state=".$qrystring."</td></tr>";
			$message .= "</table>";
			$message .= "</body></html>";

			$headers = 
				'Return-Path: ' . $emailfrom . "\r\n" . 
				'From: ' . $fromname . ' <' . $emailfrom . '>' . "\r\n" . 
				'X-Priority: 3' . "\r\n" . 
				'X-Mailer: PHP ' . phpversion() .  "\r\n" . 
				'Reply-To: ' . $fromname . ' <' . $emailfrom . '>' . "\r\n" .
				'MIME-Version: 1.0' . "\r\n" . 
				'Content-Transfer-Encoding: 8bit' . "\r\n" . 
				'Content-Type: text/html; charset=UTF-8' . "\r\n";
			$params = '-f ' . $emailfrom;
			$sendmail = mail($emailto, $subject, $message, $headers, $params);

			if ($sendmail) {

			  return array("error" => false, "message" => 'Mail Sent');
			} 

		}catch(Exception $e){ 
			return array("error" => true, "message" => $e->getMessage());
		}	
		   
	}

	// The user was redirected back from the OAuth form with an authorization code.
	// To get the existing stripe account id of seller
	public function getAcccountCredentials($data){
		if(!$data){
			return array("error" => true, "message" => "Missing Auth Parameter");
		}
		
        if(isset($data["code"]) && $data["code"]){
            
            
            $code = $data['code'];
            try {
                $resp = \Stripe\OAuth::token([
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                ]);
                
            
                print_r($resp);
                if($resp && $resp->stripe_user_id){		
            		return array("error" => false, "message" => "Sucessfully authorized account", "result" =>$resp);
            	}
                
            } catch (\Stripe\Error\OAuth\OAuthBase $e) { 
                return array("error" => true, "message" => $e->getMessage());
            }
            
        }else if(isset($data["error"]) && $data["error"] == 'access_denied'){
           
            // The user was redirect back from the OAuth form with an error.
          
             //authorization was denied by the user
        	return array("error" => true, "message" => $data['error_description']);
        }

    }
}
// END Stripegateway Class