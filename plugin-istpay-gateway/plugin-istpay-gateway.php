<?php
/*
Plugin Name: Plugin Istpay Gateway
Plugin URI: https://istpay.com.br/
Description: Aceite pagamentos por Cartão de Crédito, Boleto ou Pix
Author: Istpay & Diogenes Junior
Version: 1.4.0
Author URI: https://www.istpay.com.br/
*/
/**
*  ------------------------------------------------------------------------------------------------
*
*
*   URLs DE ATUALIZAÇÕES DO PLUGIN
*
*
*  ------------------------------------------------------------------------------------------------
*/
require "update/plugin-update-checker.php";
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
  'https://www.diogenesjunior.com.br/diretorios/plugins/plugin-istpay-gateway/atualizacoes.json',
  __FILE__, //Full path to the main plugin file or functions.php.
  'plugin-istpay-gateway'
);

/**
*  ------------------------------------------------------------------------------------------------
*
*
*   REGISTERS
*
*
*  ------------------------------------------------------------------------------------------------
*/
add_theme_support( 'woocommerce' );

add_action( 'wp_enqueue_scripts', 'misha_register_and_enqueue_istpay' );
 
function misha_register_and_enqueue_istpay() {

	// MASCARAS INPUT
	wp_register_script( 'istpay-mask-script', plugins_url( 'js/dist/jquery.inputmask.bundle.js?v='.date("dmYHisu"), __FILE__ ), array( 'jquery' ) );
	wp_enqueue_script( 'istpay-mask-script' );

	wp_register_script( 'istpay-mask-phone-script', plugins_url( 'js/dist/inputmask/phone-codes/phone.js?v='.date("dmYHisu"), __FILE__ ), array( 'jquery' ) );
	wp_enqueue_script( 'istpay-mask-phone-script' );

	wp_enqueue_style( 'style-istpay-gateway', get_option('home')."/wp-content/plugins/plugin-istpay-gateway/css/style.css?v=".date("dmYHisu") );
	 
}






/**
*  ------------------------------------------------------------------------------------------------
*
*
*   PAGE TEMPLATES
*
*
*  ------------------------------------------------------------------------------------------------
*/
/*
add_action( 'admin_menu', 'wpse_istpay_manual_register' );

function wpse_istpay_manual_register()
{ 
    // PRINCIPAL
    add_menu_page(
        'istpay',     // page title
        'istpay',     // menu title
        'manage_options',   // capability
        'istpay-ppc',     // menu slug
        'istpay_render' // callback function
    );

   
    add_submenu_page( 'istpay-ppc', 'Configurações', 'Configurações','manage_options', 'configuracoes-istpay-ppc-ppc', 'istpay_render_configs');


}

function istpay_render(){

    $file = plugin_dir_path( __FILE__ ) . "templates/dashboard.php";

    if ( file_exists( $file ) )
        require $file;

}

function istpay_render_configs(){

    $file = plugin_dir_path( __FILE__ ) . "templates/configuracoes.php";

    if ( file_exists( $file ) )
        require $file;

}
*/

/**
*  ------------------------------------------------------------------------------------------------
*
*
*   API DE CONEXÃO istpay COTABANK (CURL)
*
*
*  ------------------------------------------------------------------------------------------------
*/

// OBTER CHAVE PUBLICA
function istpay_rsa_public_key($url_conexao){

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url_conexao.'/v1/getKey',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'GET',
		));

		$response = curl_exec($curl);

		curl_close($curl);

		$result = json_decode( $response, true );

		if($result["status"]==true):

			return $result["publicKey"];
		
		else:
		
			return "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAkP+O2bQZIj6ddp/pZI4s\nOrPZ/NHyV7ANLZW3ZsVstEE8HtHoHaFkBZBJ7E/7tF60mgn67jHNP1zmefxShQfJ\nbAI1eBIjS2iouN2xlvZTT/LL1w4rSVf4m9/3iRjjS2U3GpFVTFgxvIxsAlq3sZCP\nxd89ua1Z637tgGqac1VbAuPDA2UhAk/uYWbgE+aQT1kMqS00dtSZHEfIUNoFJ+Kk\nxBm2eYBc5nqmvbdvNENWlN8Ai9LbAgRUPX3vmxDmUt0JSttOfwzGFIRKgGBW78MM\naQDiO050fVxhHre8RWWt8URrLbRrhuNquOv3jTk56jnI//+K3Y68n/22XMAmyzP/\n5wIDAQAB\n-----END PUBLIC KEY-----\n";
		
		endif;
		
}


// OBTER TOKEN DE CONEXÃO API
function istpay_token($login,$senha,$url_api){
     	
     	function EncryptData($source,$pub_key){
				  
		   openssl_get_publickey($pub_key);
		   openssl_public_encrypt($source,$crypttext,$pub_key);
				  
		   return(base64_encode($crypttext));

		}

        $curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url_api."/api/v1/acesso/autenticar",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>'{
		  "usuario": "'.$login.'",
		  "senha": "'.$senha.'"
		}',
		  CURLOPT_HTTPHEADER => array(
		    'Content-Type: application/json'
		  ),
		));

		$response = curl_exec($curl);

		curl_close($curl);
		
		$result = json_decode( $response, true );

		if($result["status"]==true):

			return $result["token"];
		
		else:
		
			return false;
		
		endif;

} 

// SALVAR O CALLBACK PARA NOTIFICAÇÃO
function salvar_callback_notificacao($order_id,$tid){

	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://servidorseguro.cloud/istpay/cobranca/?pedido='.$order_id.'&tid='.$tid.'&callback_notification='.get_option("home").'/wp-json/istpay/v1/notificacao',
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'GET',
	));
	
	$response = curl_exec($curl);

	error_log("NOTIFICAÇÃO ISTPAY:");
	error_log(print_r($response,true));
	
	curl_close($curl);
	echo $response;

    return TRUE;

}


// COBRAR CARTÃO DE CREDITO
function istpay_cartao_de_credito($login_istpay,$order,
								   $url_api,$token,$callback_url,
								   $istpay_bandeira_cartao,
								   $istpay_numero_cartao,
								   $istpay_nome_cartao,
								   $istpay_validade_cartao,
								   $istpay_cvv_cartao,
								   $istpay_num_parcelas){

	   $order_data = $order->get_data();

	    $cpf = str_replace(".","",get_post_meta($order->get_id(),'_billing_cpf',true));
		$cpf = str_replace("-","",$cpf);
		$cpf = str_replace(" ","",$cpf);

		$istpay_numero_cartao = str_replace("-","",$istpay_numero_cartao);
		$istpay_numero_cartao = str_replace("_","",$istpay_numero_cartao);

		$istpay_cvv_cartao = str_replace("_","",$istpay_cvv_cartao);

		if($istpay_num_parcelas=="") $istpay_num_parcelas = 1;
		if($istpay_num_parcelas=="1") $tipo_payment = "V";
		if($istpay_num_parcelas>"1") $tipo_payment = "L";

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url_api.'/api/v1/pedidos',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>'{
			"lojista": {
				"username_ec": "'.$login_istpay.'",
				"cod_ec": "",
				"doc_ec": ""
			},
		  "cliente": {
		    "nome": "'.$order_data['billing']['first_name'].' '.$order_data['billing']['last_name'].'",
		    "email": "'.$order_data['billing']['email'].'",
		    "documento": "'.$cpf.'",
		    "tipo": "pf",
		    "telefone": "'.$order_data['billing']['phone'].'",
		    "endereco": {
		      "logradouro": "'.get_post_meta($order->get_id(),'_billing_number',true).', '.$order_data['billing']['address_1'].', '.get_post_meta($order->get_id(),'_billing_neighborhood',true).'",
		      "complemento": "",
		      "cep": "'.str_replace("-", "", $order_data['billing']['postcode']).'",
		      "cidade": "'.$order_data['billing']['city'].'",
		      "sigla_uf": "'.$order_data['billing']['state'].'"
		    }
		  },
		  "itens": [
		    {
		      "descricao": "Novo pedido '.get_bloginfo( 'name' ).' #'.$order->get_id().' ",
		      "valor_total": '.$order_data['total'].',
		      "quantidade": 1
		    }
		  ],
		  "pagamentos": [
		    {
		      "cartao_credito": {
		        "parcelamento": 1,
		        "titular_cartao": "'.$istpay_nome_cartao.'",
		        "numero_cartao": "'.$istpay_numero_cartao.'",
		        "validade_cartao": "'.$istpay_validade_cartao.'",
		        "cvv": "'.$istpay_cvv_cartao.'"
		      }
		    }
		  ]
		}',
		  CURLOPT_HTTPHEADER => array(
		    'token: '.$token,
		    'Authorization: Bearer '.$token,
		    'Content-Type: application/json'
		  ),
		));

		$response = curl_exec($curl);

		curl_close($curl);

		$result = json_decode( $response, true );

		error_log(print_r($result,true));

		// RETORNAR OS DADOS
		if($result["status"]==true):

			add_post_meta($order->get_id(), 'instpay_metodo_de_pagamento', "cc", TRUE);
			add_post_meta($order->get_id(), 'tid', $result["tid"], TRUE);
			add_post_meta($order->get_id(), 'message', $result["message"], TRUE);

			salvar_callback_notificacao($order->get_id(),$result["tid"]);

			return array("status" => $result["status"], "message" => $result["message"], "tid" => $result["tid"]);
		
		else:
		
			return false;
		
		endif;

}

// PAGAR COM PIX
function istpay_pix($login_istpay,$order,$url_de_conexao_api_pix_boleto,$login_conexao_pix_boleto,$senha_conexao_pix_boleto,$callback_url){

	$curl = curl_init();

	$order_data = $order->get_data();

	$valor_pedido = $order_data['total'];

	curl_setopt_array($curl, array(
	CURLOPT_URL => $url_de_conexao_api_pix_boleto.'/v1/pix/request-payment-code',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_POSTFIELDS =>'{
		"username":"'.$login_istpay.'",
		"value":"'.$valor_pedido.'"
	}',
	CURLOPT_HTTPHEADER => array(
		'ci: '.$login_conexao_pix_boleto.'',
		'cs: '.$senha_conexao_pix_boleto.'',
		'Content-Type: application/json'
	),
	));

	$response = curl_exec($curl);

	$result = json_decode( $response, true );

		// RETORNAR OS DADOS
		if($result["response"]=="OK"):

			add_post_meta($order->get_id(), 'instpay_metodo_de_pagamento', "pix", TRUE);
			add_post_meta($order->get_id(), 'codigo_pix', $result["paymentCode"], TRUE);
			add_post_meta($order->get_id(), 'id_transacao_instpay', $result["idTransaction"], TRUE);

			salvar_callback_notificacao($order->get_id(),$result["idTransaction"]);

			return array("status" => $result["response"], "paymentCode" => $result["paymentCode"], "tid" => $result["idTransaction"]);
		
		else:
		
			return false;
		
		endif;

}


// COBRAR BOLETO BANCÁRIO
function istpay_boleto_bancario($login_istpay,$order,$url_de_conexao_api_pix_boleto,$login_conexao_pix_boleto,$senha_conexao_pix_boleto,$callback_url){

	    $order_data = $order->get_data();

		$valor_pedido = $order_data['total'];

	    $cpf = str_replace(".","",get_post_meta($order->get_id(),'_billing_cpf',true));
		$cpf = str_replace("-","",$cpf);
		$cpf = str_replace(" ","",$cpf);

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $url_de_conexao_api_pix_boleto.'/v1/boleto/request-boleto',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS =>'{
				"username":"'.$login_istpay.'",
				"value":"'.$valor_pedido.'",
				"dueDate":"'.date("Y-m-d").'",
				"description":"Pedido #'.$order->get_id().'",
				"payerData": {
					"name":"'.$order_data['billing']['first_name'].'",
					"document":"'.$cpf.'",
					"address": {
						"codIbge":"",
						"street":"'.$order_data['billing']['address_1'].'",
						"number":"'.get_post_meta($order->get_id(),'_billing_number',true).'",
						"complement":"'.$order_data['billing']['address_2'].'",
						"zipCode":"'.str_replace("-", "", $order_data['billing']['postcode']).'",
						"neighborhood":"'.get_post_meta($order->get_id(),'_billing_neighborhood',true).'",
						"city":"'.$order_data['billing']['city'].'",
						"state":"'.$order_data['billing']['state'].'"
					}
				}
			}',
			CURLOPT_HTTPHEADER => array(
				'ci: '.$login_conexao_pix_boleto.'',
				'cs: '.$senha_conexao_pix_boleto.'',
				'Content-Type: application/json'
			),
		  ));
		  
		$response = curl_exec($curl);

		curl_close($curl);

		$result = json_decode( $response, true );

		//error_log(print_r($result,true));

		// RETORNAR OS DADOS
		if($result["response"]=="OK"):

			add_post_meta($order->get_id(), 'instpay_metodo_de_pagamento', "boletobancario", TRUE);
			add_post_meta($order->get_id(), 'codigo_barras', $result["digitableLine"], TRUE);
			add_post_meta($order->get_id(), 'id_transacao_instpay', $result["idTransaction"], TRUE);

			salvar_callback_notificacao($order->get_id(),$result["idTransaction"]);

			return array("status" => "OK", "barcode" => $result["barcode"], "digitableLine" => $result["digitableLine"], "tid" => $result["idTransaction"]);
		
		else:
		
			return false;
		
		endif;

}


// COBRAR LINK DE PAGAMENTO
function istpay_link_de_pagamento($login_istpay,$order,$url_api,$token,$callback_url,$max_parcelas){

	    if($max_parcelas==""):
	    	$max_parcelas = 6;
	    endif;

	    $order_data = $order->get_data();

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url_api.'/v2/paymentLink',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>'{
		    "site": "'.get_bloginfo( 'name' ).'",
		    "transactionDescription": "'.get_bloginfo( 'name' ).'",
		    "callbackUrl": "'.$callback_url.'", 
		    "maxInstallments": "'.$max_parcelas.'",
		    "transactionIdentifier": "'.$order->get_id().'",
		    "products": [
		     {
		       "name": "Pedido #'.$order->get_id().'", 
		       "quantity": 1
		     }],
		    "productsType": "Payment", 
		    "productsValue": '.$order_data['total'].', 
		    "shipping": {
		       "type": "WithoutShipping"
		    }
		}',
		  CURLOPT_HTTPHEADER => array(
		    'x-access-token: '.$token,
		    'Content-Type: application/json'
		  ),
		));

		$response = curl_exec($curl);

		curl_close($curl);

		$result = json_decode( $response, true );

		// RETORNAR OS DADOS
		if($result["status"]==true):

			return $result["link"];
		
		else:
		
			return false;
		
		endif;

}



/**
*  ------------------------------------------------------------------------------------------------
*
*
*   GATEWAY WOOCOMMERCE
*
*
*  ------------------------------------------------------------------------------------------------
*/
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'istpay_gateway_class' );
function istpay_gateway_class( $gateways ) {
	$gateways[] = 'WC_Istpay_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'istpay_init_gateway_class' );
function istpay_init_gateway_class() {

	class WC_Istpay_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {

		$this->id = 'istpaygateway'; // payment gateway plugin ID
		$this->icon = 'https://istpay.com.br/wp-content/uploads/2021/08/logo_tipografia.png'; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields = true; // in case you need a custom credit card form
		$this->method_title = 'Istpay Gateway';
		$this->method_description = 'Pagamentos por cartões de crédito, boleto bancário ou PIX'; // will be displayed on the options page

		// gateways can support subscriptions, refunds, saved payment methods,
		// but in this tutorial we begin with simple payments
		$this->supports = array(
			'products'
		);

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled = $this->get_option( 'enabled' );
		$this->testmode = 'yes' === $this->get_option( 'testmode' );
		$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
		$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );

		// GERAIS E CARTÃO DE CREDITO
		$this->ambiente  = $this->get_option( 'ambiente' );
		
		$this->login_istpay  = $this->get_option( 'login_istpay' );

		$this->ativar_cartaodecredito = $this->get_option( 'ativar_cartaodecredito' );
		$this->max_parcelas = $this->get_option( 'max_parcelas' );
		$this->ativar_boletobancario = $this->get_option( 'ativar_boletobancario' );
		$this->ativar_pix = $this->get_option( 'ativar_pix' );
		$this->url_de_conexao_api = $this->get_option( 'url_de_conexao_api' );
		$this->url_de_conexao_api_producao = $this->get_option( 'url_de_conexao_api_producao' );
		$this->login_conexao = $this->get_option( 'login_conexao' );
		$this->senha_conexao = $this->get_option( 'senha_conexao' );

		// PIX E BOLETO BANCARIO
		$this->url_de_conexao_api_pix_boleto = $this->get_option( 'url_de_conexao_api_pix_boleto' );
        $this->login_conexao_pix_boleto = $this->get_option( 'login_conexao_pix_boleto' );
		$this->senha_conexao_pix_boleto = $this->get_option( 'senha_conexao_pix_boleto' );


		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// We need custom JavaScript to obtain a token
		add_action( 'wp_enqueue_scripts', array( $this, 'istpay_payment_scripts' ) );
		
		// You can also register a webhook here
		// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

 		}

		/**
 		 *  OPÇÕES DO PLUGIN NA TELA DE ADMINSTRAÇÃO DO WOOCOMMERCE
 		 */
 		public function init_form_fields(){

 			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Habilitar/Desabilitar',
					'label'       => 'Habilitar Istpay Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Título',
					'type'        => 'text',
					'description' => 'Título para ser exibido para o usuário na página de checkout.',
					'default'     => 'Istpay Gateway',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Descrição',
					'type'        => 'textarea',
					'description' => 'Texto para ser exibido para o usuário na página de checkout.',
					'default'     => 'Pagamentos por cartões de crédito, boleto bancário ou PIX.',
				),
				'login_istpay' => array(
					'title'       => 'Login usuário Istpay',
					'label'       => 'Seu usário Istpay para acesso a API',
					'type'        => 'text',
					'description' => 'Seu usário Istpay para acesso a API',
					'desc_tip'    => true,
				),
				'url_de_conexao_api' => array(
					'title'       => 'URL de conexão com API (Cartão de crédito)',
					'label'       => 'URL de integração com a API Istpay para cartão de crédito',
					'type'        => 'text',
					'description' => 'Realizar transações no ambiente Istpay para cartão de crédito',
					'desc_tip'    => true,
				),
				'max_parcelas' => array(
					'title'       => 'Número máximo de parcelas',
					'type'        => 'number',
					'description' => 'Quantas parcelas máximas irá aceitar com cartão de crédito? (Parcelas com valor mínimo de R$5,00)',
					'default'     => '6',
					'min'         => '1',
					'max'         => '12',
					'desc_tip'    => true,
				),
				'login_conexao' => array(
					'title'       => 'Login de acesso (Cartão de Crédito)',
					'type'        => 'text',
					'description' => 'Login de acesso especifico de acesso à API',
					'desc_tip'    => true,
				),
				'senha_conexao' => array(
					'title'       => 'Senha de acesso (Cartão de Crédito)',
					'type'        => 'password',
					'description' => 'Senha de acesso especifica de acesso à API',
					'desc_tip'    => true,
				),
				'url_de_conexao_api_pix_boleto' => array(
					'title'       => 'URL de conexão com API (PIX e Boleto bancário)',
					'label'       => 'URL de integração com a API Istpay para cartão de crédito',
					'type'        => 'text',
					'description' => 'Realizar transações no ambiente Istpay para cartão de crédito',
					'desc_tip'    => true,
				),
				'login_conexao_pix_boleto' => array(
					'title'       => 'Login de acesso CI (PIX e Boleto bancário)',
					'type'        => 'text',
					'description' => 'Login de acesso especifico de acesso à API',
					'desc_tip'    => true,
				),
				'senha_conexao_pix_boleto' => array(
					'title'       => 'Senha de acesso CS (PIX e Boleto bancário)',
					'type'        => 'password',
					'description' => 'Senha de acesso especifica de acesso à API',
					'desc_tip'    => true,
				)
			);

		
	 	}

		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
			
			* $this->ambiente
		    * $this->ativar_cartaodecredito
			* $this->max_parcelas 
			* $this->ativar_boletobancario
			* $this->ativar_linkdepagamento 
			* $this->url_de_conexao_api
			* $this->url_de_conexao_api_producao
			* $this->login_conexao
			* $this->senha_conexao

		 */
		public function payment_fields() {

			// PROCESSAR PARCELAS
			$total_carrinho = WC()->cart->get_cart_contents_total();
			$tot_parcelas   = 0;
			$divisor        = 1;
			$html_parcelas  = "";
			
			while($tot_parcelas<12 && $tot_parcelas<$this->max_parcelas):

				$valor_parcela = $total_carrinho / $divisor;

				if($valor_parcela>=5):
					$html_parcelas = $html_parcelas . '<option value="'.$divisor.'">'.$divisor.'x de R$'.number_format($valor_parcela,2,",",".").'</option>';
				else:
					$tot_parcelas = 13;
				endif;

				$divisor++;
				$tot_parcelas++;

			endwhile;
			// FIM PROCESSAR PARCELAS


			$html_cc = "";
			$html_boleto = "";
			$html_link  = "";
			$html_pix  = "";


		
				$html_cc = '

					<li class="payment_method_woo-istpay-custom">
							<input id="istpay_payment_cartaodecredito" type="radio" class="input-radio" name="istpay_meio_de_pagamento" value="woo-istpay-cartaodecredito" checked="checked" data-order_button_text="Pagar com cartão de crédito" >

						    <label for="istpay_payment_cartaodecredito">
											Pagar com <b>Cartão de Crédito</b></label>

					</li>

			  ';

			  
			  $html_pix = '
			  
			  <li class="payment_method_woo-istpay-custom">
						<input id="istpay_payment_pix" type="radio" class="input-radio" name="istpay_meio_de_pagamento" value="woo-istpay-pix" data-order_button_text="Pagar com PIX" >

						<label for="istpay_payment_pix">
										Pagar com <b>PIX</b></label>

			  </li>

			 
			  '; 


			  $html_boleto = '
			  
			   <li class="payment_method_woo-istpay-custom">
						<input id="istpay_payment_boletobancario" type="radio" class="input-radio" name="istpay_meio_de_pagamento" value="woo-istpay-boletobancario" data-order_button_text="Pagar com Boleto Bancário" >

						<label for="istpay_payment_boletobancario">
										Pagar com <b>Boleto Bancário</b></label>

				</li>
			 
			  ';


			$html = '<ul class="wc_istpay_payment_methods istpay_payment_methods istpay_methods">

						'.$html_cc.'
						'.$html_link.'
						'.$html_pix.'
						'.$html_boleto.'

				     </ul>

					<!-- CARTÃO DE CRÉDITO -->
					<div class="istpay-pagamento-content istpay-pagamento-cartaodecredito">
				      		
				      	  <input type="hidden" name="istpay_bandeira_cartao" id="istpay_bandeira_cartao" value="" />
				      	  	
					      <div class="istpay-form-group">
					      	<label>Número do cartão</label>
					      	<input type="tel" class="istpay-form-control" autocomplete="off" placeholder="9999-9999-9999-9999" onchange="verificarBandeiraCartao(this.value)" name="istpay_numero_cartao" id="istpay_cc_cardholder" />
					      </div>

					      <div class="istpay-form-group">
					      	<label>Nome do títular</label>
					      	<input type="text" class="istpay-form-control" autocomplete="off" placeholder="Exatamente como escrito no cartão" name="istpay_nome_cartao" id="istpay_cc_nometitulo" />
					      </div>

					      <div class="istpay-form-group">
					      	<label>Validade</label>
					      	<input type="tel" class="istpay-form-control" autocomplete="off" placeholder="MM/AA" name="istpay_validade_cartao" id="istpay_cc_validade" />
					      </div>

					      <div class="istpay-form-group">
					      	<label>CVV</label>
					      	<input type="tel" class="istpay-form-control" autocomplete="off" placeholder="CVV" name="istpay_cvv_cartao" id="istpay_cc_cvv" />
					      </div>

					      <div class="istpay-form-group">
					      	<label>Número de parcelas</label>
					      	<select class="istpay-form-control" name="istpay_num_parcelas" id="istpay_cc_parcelas">
					      	  '.$html_parcelas.'
					      	</select>
					      </div>

				    </div>

				    <!-- 
					

					<div class="istpay-pagamento-content istpay-pagamento-linkdepagamento">
				      Na próxima tela, você será redirecionado para o link de pagamento.
				    </div>

					
				    -->

					<div class="istpay-pagamento-content istpay-pagamento-pix">
				      Na próxima tela, você verá o QRCODE ou código PIX para o pagamento.
				    </div>

					<div class="istpay-pagamento-content istpay-pagamento-boletobancario">
				       Na próxima tela, você visualizará o código de barras para pagamento.
				    </div>


				    <script>

				    	// ESTANCIAR AS MASCARAS
				    	jQuery("#istpay_cc_cardholder").inputmask("9999-9999-9999-9999");
						jQuery("#istpay_cc_validade").inputmask("99/99");
						jQuery("#istpay_cc_cvv").inputmask("999");

				    </script>

			';


			echo $html;

		}

		/*
		 *  CUSTOM JAVASCRIPTS PARA PAGAMENTO COM istpay
		 */
	 	public function istpay_payment_scripts() {

				// APENAS EM PÁGINAS DE CARRINHO E CHECKOUT
				if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
					return;
				}

				// istpay HABILITADO
				if ( 'no' === $this->enabled ) {
					return;
				}

				// CASO O USUÁRIO NÃO TENHA INFORMADO O LOGIN E SENHA DE CONEXÃO
				if ( empty( $this->login_conexao ) || empty( $this->senha_conexao ) ) {

					wc_print_notice( "Erro istpay: Login ou senha de acesso à API não foram informados nas configurações do WooCommerce", "error" );

					return;

				}

				// let's suppose it is our payment processor JavaScript that allows to obtain a token
				//wp_enqueue_script( 'misha_js', 'https://www.mishapayments.com/api/token.js' );

				// and this is our custom JS in your plugin directory that works with token.js
				wp_register_script( 'woocommerce_istpay', plugins_url( 'js/istpay.js?v='.date("dmYHisu"), __FILE__ ), array( 'jquery' ) );

				// in most payment processors you have to use PUBLIC KEY to obtain a token
				//wp_localize_script( 'woocommerce_misha', 'misha_params', array(
				//	'publishableKey' => $this->publishable_key
				//));

				wp_enqueue_script( 'woocommerce_istpay' );
				
				return true;
	
	 	}

		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {

		  return true;

		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {

		    global $woocommerce;
 
			// we need it to get any order detailes
			$order = wc_get_order( $order_id );

			//$order->payment_complete();

			/*
				$ambiente                    = $this->ambiente;
				$ativar_cartaodecredito      = $this->ativar_cartaodecredito;
				$base_max_parcelas           = $this->max_parcelas;
				$ativar_boletobancario       = $this->ativar_boletobancario;
				$ativar_linkdepagamento      = $this->ativar_linkdepagamento;
				$ativar_pix     			 = $this->ativar_pix;
				$url_de_conexao_api          = $this->url_de_conexao_api;
				$url_de_conexao_api_producao = $this->url_de_conexao_api_producao;
				$login_conexao               = $this->login_conexao;
				$senha_conexao               = $this->senha_conexao;

				url_de_conexao_api_pix_boleto
				login_conexao_pix_boleto
				senha_conexao_pix_boleto
			*/

			// GERAIS
			$title                 = $this->title;
			$description           = $this->description;

			$login_istpay		  = $this->login_istpay;

			// DADOS CARTÃO
			$url_de_conexao_api    = $this->url_de_conexao_api;
			$max_parcelas          = $this->max_parcelas; 
			$login_conexao         = $this->login_conexao;
			$senha_conexao         = $this->senha_conexao;

			// DADOS PIX E BOLETO
			$url_de_conexao_api_pix_boleto = $this->url_de_conexao_api_pix_boleto;
			$login_conexao_pix_boleto      = $this->login_conexao_pix_boleto;
			$senha_conexao_pix_boleto      = $this->senha_conexao_pix_boleto;

			// POST FIELDS CARTÃO DE CRÉDITO
			$istpay_meio_de_pagamento = $_POST['istpay_meio_de_pagamento'];
			$istpay_bandeira_cartao   = $_POST['istpay_bandeira_cartao'];
			$istpay_numero_cartao     = $_POST['istpay_numero_cartao'];
			$istpay_nome_cartao       = $_POST['istpay_nome_cartao'];
			$istpay_validade_cartao   = $_POST['istpay_validade_cartao'];
			$istpay_cvv_cartao        = $_POST['istpay_cvv_cartao'];
			$istpay_num_parcelas      = $_POST['istpay_num_parcelas'];


			// PAGAR COM CARTÃO DE CRÉDITO
			if($istpay_meio_de_pagamento=="woo-istpay-cartaodecredito"):

						$token = istpay_token($login_conexao,$senha_conexao,$url_de_conexao_api);
						//$token = "a5ffe718-e2d9-4bb3-9df5-be86b4fa36b2ff873bb44ed1958ce7b285a092dd33fccb8e-98e9-4ece-9631-a006db856c74";

						error_log("CHAVE RSA istpay GERADA:");
						error_log($chave_rsa);

						$order->add_order_note( 'Método de pagamento escolhido: <b>Istpay Cartão de crédito</b>. Número de parcelas: <b>'.$istpay_num_parcelas.'</b>', true );
						
						// REALIZAR O PAGAMENTO
						$status_pagamento = istpay_cartao_de_credito($login_istpay, $order,
																	  $url_de_conexao_api,
																	  $token,
																	  $this->get_return_url($order),
																	  $istpay_bandeira_cartao,
																	  $istpay_numero_cartao,
																	  $istpay_nome_cartao,
																	  $istpay_validade_cartao,
																	  $istpay_cvv_cartao,
																	  $istpay_num_parcelas);

						if($status_pagamento["status"]==true && $status_pagamento["message"]=="CONFIRMED"):

							// LIMPAR O CARRINHO
							$woocommerce->cart->empty_cart();

							// ATUALIZAR O STATUS PARA PROCESSANDO
							$order->update_status( 'wc-processing' );

							wc_reduce_stock_levels( $order->get_id() );

							// REDIRECIONAR PARA OUTRA PÁGINA
							return array(
								'result' => 'success',
								'redirect' => $this->get_return_url( $order )
								
							);
				 
							return true;

						else:

								error_log("NÃO CONSEGUIMOS REALIZAR PAGAMENTO COM CARTÃO DE CRÉDITO istpay");

								return array(
									'result' => 'error'
								);

								return false;

						endif;	

			endif;
			// FIM PAGAMENTO CARTÃO DE CRÉDITO


			// PAGAR COM PIX
			if($istpay_meio_de_pagamento=="woo-istpay-pix"):

				// REALIZAR O PAGAMENTO
				$status_pagamento = istpay_pix($login_istpay, $order,
				$url_de_conexao_api_pix_boleto,
				$login_conexao_pix_boleto,
				$senha_conexao_pix_boleto,
				$this->get_return_url($order));

				if($status_pagamento["status"]=="OK" && $status_pagamento["paymentCode"]!=""):

					// LIMPAR O CARRINHO
					$woocommerce->cart->empty_cart();

					// ATUALIZAR O STATUS PARA AGUARDANDO PAGAMENTO
					$order->update_status( 'wc-pending' );

					wc_reduce_stock_levels( $order->get_id() );

					$order->add_order_note( 'Método de pagamento escolhido <b>Istpay PIX</b>. Código PIX copia e cola: '.$status_pagamento["paymentCode"], true );

					// REDIRECIONAR PARA OUTRA PÁGINA
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
						
					);
		 
					return true;

				else:

						error_log("NÃO CONSEGUIMOS REALIZAR PAGAMENTO COM PIX istpay");

						return array(
							'result' => 'error'
						);

						return false;

				endif;	

			endif;
			// FIM PAGAMENTO PIX


			// PAGAR COM BOLETO BANCÁRIO
			if($istpay_meio_de_pagamento=="woo-istpay-boletobancario"):

				// REALIZAR O PAGAMENTO
				$status_pagamento = istpay_boleto_bancario($login_istpay, $order,
				$url_de_conexao_api_pix_boleto,
				$login_conexao_pix_boleto,
				$senha_conexao_pix_boleto,
				$this->get_return_url($order));

				if($status_pagamento["status"]=="OK" && $status_pagamento["digitableLine"]!=""):

					// LIMPAR O CARRINHO
					$woocommerce->cart->empty_cart();

					// ATUALIZAR O STATUS PARA AGUARDANDO PAGAMENTO
					$order->update_status( 'wc-pending' );

					wc_reduce_stock_levels( $order->get_id() );

					$order->add_order_note( 'Método de pagamento escolhido <b>Istpay Boleto Bancário</b>. Código de barras do boleto: '.$status_pagamento["digitableLine"], true );

					// REDIRECIONAR PARA OUTRA PÁGINA
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
						
					);
		 
					return true;

				else:

						error_log("NÃO CONSEGUIMOS REALIZAR PAGAMENTO COM BOLETO BANCÁRIO istpay");

						return array(
							'result' => 'error'
						);

						return false;

				endif;	

			endif;
			// FIM PAGAMENTO BOLETO BANCÁRIO
 
					
	 	}


		/*
		 *  WEBHOOK DE PROCESSAMENTO
		 */
		public function webhook() {

			// OBTER O PEDIDO
		    $order = wc_get_order( $_GET['id'] );
			
			// SETAR O PAGAMENTO COMO COMPLETO
			$order->payment_complete();

			// REDUZIR A QUANTIDADE DE ESTOQUE
			$order->reduce_order_stock();

			// DEBUG NO LOG
			update_option('webhook_debug', $_GET);

			return true;
					
	 	}
 	}
}



// RETORNO INFO DE PAGAMENTO PARA O USUÁRIO
add_action( 'woocommerce_thankyou', 'istpay_add_content_thankyou', 4 );
  
function istpay_add_content_thankyou($order_id) {

	// IMPRIMIR AS INFORMAÇÕES PARA PAGAMENTO DO BOLETO NA TELA DE OBRIGADO
	if(get_post_meta($order_id,"instpay_metodo_de_pagamento",true)=="boletobancario"):

			echo '<h2 class="istpay-titulo-boleto" style="text-align:center">
					<small style="display:block;margin-left:auto;margin-right:auto">CÓDIGO DO BOLETO</small>
					'.get_post_meta($order_id,"codigo_barras",true).'
			</h2>
			<p style="text-align:center">
				<button id="retornoCopiaCodigo" type="button" class="button" onclick="copyCodeIstpay(`'.get_post_meta($order_id,"codigo_barras",true).'`)">
					Copiar código de barras
				</button>
			</p>
			<!--<p style="text-align:center">
				<a href="#" class="button" title="Visualizar boleto bancário">Visualizar boleto bancário</a>
			</p>-->

			';

	endif;


	if(get_post_meta($order_id,"instpay_metodo_de_pagamento",true)=="pix"):

		echo '<h2 class="istpay-titulo-boleto" style="text-align:center">
				<small style="display:block;margin-left:auto;margin-right:auto">CÓDIGO PIX COPIA E COLA</small>
				'.get_post_meta($order_id,"codigo_pix",true).'
		</h2>
		<p style="text-align:center">
			<button id="retornoCopiaCodigo" type="button" class="button" onclick="copyCodeIstpay(`'.get_post_meta($order_id,"codigo_pix",true).'`)">
				Copiar código pix
			</button>
		</p>
		<p style="text-align:center">
			Acesse o aplicativo do seu banco, selecione a opção "PIX" e copie e cole o código acima.
		</p>

		';

	endif;
   
   
}


		

?>