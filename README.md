# Plugin Istpay Gateway para WooCommerce (legado)

Esse repositório contem os arquivos do código fonte do plugin Istpay Gateway para WooCommerce, até a versão 1.4.0, além de alguns comentários sobre a abordagem do desenvolvimento, e a infraestrutura de negócio da Istpay pagamentos no período de Abril de 2022.

## Sobre a API

As API's de conexão são de total responsabilidade da Instpay, e por tanto, toda a documentação a respeito da API poderão ser fornecidas diretamente pela equipe da Istpay.

*API CARTÃO DE CRÉDITO:* https://api.globalpaysolucoes.com.br

*API BOLETO e PIX:* https://d8dbbf91-61c0-4f83-802f-a90889fb2550.mock.pstmn.io

Obviamente, cada API acima, tem seus próprios meios de autenticação, informação essa, que pode ser fornecedia pela equipe da Istpay.

*CARTÃO DE CRÉDITO:* Autenticação usando Login e Senha
*BOLETO E PIX:* Autenticação usando CI e CS (Cliente ID e Client Secret)

## Fluxo de comunicação

O fluxo de conexão acontece da seguinte maneira:

```
					┌─────────────────────────┐
					│                         │
					│                         │
					│   Pedido WooCommerce    │
					│                         │
					│                         │
					└───────────┬─────────────┘
					            │
					            │
					            │
					┌───────────▼─────────────┐
					│                         │
					│                         │
					│  API ISTPAY CC/BC/PIX   │
					│                         │
					│                         │
					└───────────┬─────────────┘
					            │
					            │
					            │
					┌───────────▼─────────────┐
					│                         │
					│                         │
					│     Retorno com TID     │
					│                         │
					│                         │
					└─────────────────────────┘
```

O ambiente da Istpay, tanto para Cartão de Crédto, como para Boleto bancário e PIX, não possui uma URL ou Endpoint de notificação para confirmação de pagamento, caso deseje saber se um pagamento foi confirmado ou não, você precisa acessar um webhook de notificação. *Note que:* pelo ambiente da Istpay não possuir um endpoit ou callback URL de notificação, pode ser tentador ficar realizando consultas de "hora em hora" para verificar status de pagamentos, mas isso pode ocasionar uma sobrecarga de requisições na maioria dos servidores do mercado. Cenário hipotético: 100 vendas realizadas em um dia, sendo consultadas uma vez a cada 10 minutos, ao final de 24 horas, serão ao menos 240.000 requisições e isso, exponêncialmente.


## Exemplo de chamada da API

### *Cartão de Crédito*

```
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
```

*Atenção:* A API da Istpay, apesar de exigir a bandeira do cartão, ela não fornece qualquer ferramenta para a obtenção ou tratamento dessa informação. Por causa disso, você precisa tratar no front-end ou no back-end da sua aplicação, para obter a bandeira. Um exemplo dessa aplicação, usando JavaScript seria:

```
			function verificarBandeiraCartao(numeroCartao){

				var bandeira = istpayCreditCard.getCardFlag(numeroCartao);

				//console.log("MATCH BANDEIRA CARTAO:");
				//console.log(bandeira);

				if(bandeira!=false){

					jQuery("#istpay_bandeira_cartao").val(bandeira);

				}

			}



			var istpayCreditCard = {

			    getCardFlag: function(cardnumber) {
			        var cardnumber = cardnumber.replace(/[^0-9]+/g, '');

			        var cards = {
			            VISA      : /^4[0-9]{12}(?:[0-9]{3})/,
			            MASTERCARD : /^5[1-5][0-9]{14}/,
			            DINERS    : /^3(?:0[0-5]|[68][0-9])[0-9]{11}/,
			            AMEX      : /^3[47][0-9]{13}/,
			            ELO  : /^6(?:011|5[0-9]{2})[0-9]{12}/,
			            HIPERCARD  : /^606282|^3841(?:[0|4|6]{1})0/,
			            ELO        : /^4011(78|79)|^43(1274|8935)|^45(1416|7393|763(1|2))|^50(4175|6699|67[0-6][0-9]|677[0-8]|9[0-8][0-9]{2}|99[0-8][0-9]|999[0-9])|^627780|^63(6297|6368|6369)|^65(0(0(3([1-3]|[5-9])|4([0-9])|5[0-1])|4(0[5-9]|[1-3][0-9]|8[5-9]|9[0-9])|5([0-2][0-9]|3[0-8]|4[1-9]|[5-8][0-9]|9[0-8])|7(0[0-9]|1[0-8]|2[0-7])|9(0[1-9]|[1-6][0-9]|7[0-8]))|16(5[2-9]|[6-7][0-9])|50(0[0-9]|1[0-9]|2[1-9]|[3-4][0-9]|5[0-8]))/,
			            JCB        : /^(?:2131|1800|35\d{3})\d{11}/,
			            AURA      : /^(5078\d{2})(\d{2})(\d{11})$/
			        };

			        for (var flag in cards) {
			            if(cards[flag].test(cardnumber)) {
			                return flag;
			            }
			        }

			        return false;
			    }

			}
```




### *PIX*

```
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

```

*Atenção:* Na ocasião do desenvolvimento, a API da Istpay não retorna o QRCODE para pagamento PIX, apenas o código "Copia e Cola".


### *Boleto Bancário*

```
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
```

*Atenção:* Na ocasião do desenvolvimento, a API da Istpay não retorna o PDF já renderizado do boleto bancário, apenas o código de barras, e um código base64 para a geração do PDF de forma manual. Não recomendo transferir essa renderização do base64 para o servidor do cliente, por causa de limitações da maioria dos servidores do mercado (em especifico versões do PHP diferentes e ambientes de hospedagem baseados em arquitetura Windows);




## Contato
Em caso de dúvidas sobre o desenvolvimento, você pode enviar um e-mail para diogenesjunior.ti@gmail.com.

Em caso de dúvidas sobre o ambiente, negócio ou questões comerciais, você pode entrar em contato diretamente com a equipe Istpay.



