# Módulo de Integração Gerencianet para VirtueMart Oficial - Versão 0.1.0 #

O módulo Gerencianet para VirtueMart permite receber pagamentos por meio do checkout transparente da nossa API.
Compatível com o Virtuemart 3 e Joomla! 2.5.

Este é o Módulo Oficial de integração fornecido pela [Gerencianet](https://gerencianet.com.br/) para VirtueMart. Com ele, o proprietário da loja pode optar por receber pagamentos por boleto bancário e/ou cartão de crédito. Todo processo é realizado por meio do checkout transparente. Com isso, o comprador não precisa sair do site da loja para efetuar o pagamento.

Algumas informações como "CPF", "número do endereço", "bairro" e "data de nascimento" poderão ser solicitados no momento do pagamento, caso os campos não sejam configurados conforme indicado.

Caso você tenha alguma dúvida ou sugestão, entre em contato conosco pelo site [Gerencianet](https://gerencianet.com.br/).

## Instalação

1. Faça o download da [última versão](auto/) do plugin.
2. Acesse o link em sua loja "Extensions" -> "Manage" -> "Install" e envie o arquivo 'gn-api-virtuemart.zip' ou extraia o conteúdo do arquivo dentro do diretório de plugins da loja.
3. Configure o plugin conforme instruções abaixo e comece a receber pagamentos com a Gerencianet.

## Configuração

1. Crie sua conta Gerencianet ( caso não exista ).
2. Crie 3 campos extras no Virtuemart: "numero" e "bairro" "data_nascimento". O número da residência, bairro e data de nascimento são dados obrigatórios para pagamento com cartão de crédito. Se não for informado no formulário de cadastro ou no carrinho, será solicitado no ato do pagamento.
3. Habilite o plugin aqui Administrar Plugins
4. Instale Plugin por esta tela Métodos de pagamento
5. Clique em Novo Método de Pagamento e preencha as informações:
Nome do Pagamento: Cartões de crédito ou Boleto Bancário ( Gerencianet );
Publicado: Sim;
Descrição do pagamento: Pague com Cartão de Crédito ou Boleto Bancário;
Método de pagamento: Gerencianet;
Grupo de Compradores: -default-.
6. Clique em "Salvar".
7. Na aba "Configurações", preencha os dados:

Configurações do Plugin de Pagamento
* Modo de teste ( Sim ou Não )
* Client ID Desenvolvimento Conte Gerencianet > API > Aplicações > Sua Aplicação > Client ID Desenvolvimento
* Client Secret Desenvolvimento Conte Gerencianet > API > Aplicações > Sua Aplicação > Client Secret Desenvolvimento
* Client ID Produção Conte Gerencianet > API > Aplicações > Sua Aplicação > Client ID Produção
* Client Secret Produção Conte Gerencianet > API > Aplicações > Sua Aplicação > Client Secret Produção
As credenciais devem ser da sua Aplicação na Gerencianet. Para criar uma nova Aplicação, entre em sua conta Gerencianet, acesse o menu "API" e clique em "Minhas Aplicações" -> "Nova aplicação".

Campos Extras Obrigatórios
* Campo Logradouro ( do endereço )
* Campo Bairro
* Campo Número ( do endereço )
* Campo Complemento ( do endereço )
* Campo Telefone ( do cliente )
* Campo CPF ( do cliente )
* Campo Data de Nascimento ( do cliente )

Configurações de Pagamento
* Ativar Boleto (Sim ou Não)
* Ativar Cartões de Crédito (Sim ou Não)


Configurações do Boleto Bancário
* Dias para vencimento
- Desconto para pagamento no Boleto

Recomendamos que antes de disponibilizar pagamentos pela Gerencianet, o lojista realize testes de cobrança com o sandbox(ambiente de testes) ativado para verificar se o procedimento de pagamento está acontecendo conforme esperado.


## Requisitos

* Versão mínima do PHP: 5.4.0
* Versão mínima do VirtueMart: 3.0
* Versão mínima do Joomla!: 2.5
