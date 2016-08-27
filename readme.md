# HIE of One Authorization Server

HIE of One Authorization Server is a simple User Managed Access ([UMA](https://tools.ietf.org/html/draft-hardjono-oauth-umacore-14)) Server that incorporates [OAuth2](https://tools.ietf.org/html/rfc6749) and [OpenID Connect](https://openid.net/connect/) protocols to facilitate the ability of an individual to control and authorize access to his or her health information to clients such as physicians, hospitals, caregivers, and third-party applications.

## Installation
Run the following commands to install:

	sudo curl -o install.sh https://raw.githubusercontent.com/shihjay2/hieofone-as/master/install.sh  
	sudo chmod +x install.sh  
	sudo bash install.sh

## Dependencies
1. PHP
2. MySQL
3. Apache
4. CURL

## Features
1. OAuth2 OpenID Connect compliant server
2. OAuth2 OpenID Connect relying party for Google and Twitter
3. User Managed Access compliant authentication server

## How a client registers with the HIE of One Authorization Server

### Requisite conditions:
1. Patient has registered a domain name where the HIE of One Authorization Server is installed (ie domain.xyz)
2. Client software (such as an EHR with a patient portal) has the capability to make HTTPS calls (such as CURL) and is able to process JSON responses.

### Step 1: Announce patient's HIE of One email address and authorization server to the client
Patient submits his or her HIE of One email address (patient@domain.xyz) to the client (like a physician), where domain.xyz is the same domain name as where the HIE of One **authorization server** is installed.  This can be done, for example, through a EHR patient portal that has a text input that allows processing of the patient's HIE of One email address.  Alternatively, the provider would enter this email address in the EHR directly when the patient's chart is open.  A negative result results in a 404 error response.

### Step 2: Client verifies patient's authorization server
Client submits email address to the following address per [Webfinger protocol](https://tools.ietf.org/html/rfc7033) to confirm validity of the account and email address.  Here is an example call (line breaks below are just for display convenience):

	GET /.well-known/webfinger?
	resource=acct:patient@domain.xyz
	&rel=http://openid.net/specs/connect/1.0/issuer

### Step 3: Client learns authorization server endpoints
Client takes  the JSON return (specifically the <code>href subkey</code> under <code>links</code>) and when appending <code>.well-known/uma-configuration</code> to the URL, like this: https://domain.xyz/uma/.well-known/uma-configuration, client will see in JSON format all the valid UMA endpoints where client makes calls to initiate a token request, start authorization, request a permission ticket, and make requesting party claims.

### Step 4: Client dynamically registers to the authorization server
Client makes a call to the <code>dynamic_client_endpoint</code> to [register itself](https://tools.ietf.org/html/draft-ietf-oauth-dyn-reg-30).  Client will need to store the <code>client_id</code> and <code>client_secret</code> (such as in a database) going forward.  It is important for the client to consider which redirect URIs to use as one of them will need to handle the <code>claims_redirect_uri</code> parameter listed [here](#step-3:-client-makes-a-call-to-the-authorization-server-with-the-permission-ticket-given-by-the-resource-server).  Being a client only requires that you declare a scope of <code>uma_authorization</code>.  Here is an example call (line breaks below are just for display convenience):

	POST /register HTTP/1.1
	Content-Type: application/json
	Accept: application/json
	Host: server.example.com

	{
		"redirect_uris":[
			"https://client.example.org/callback",
			"https://client.example.org/callback2"]
		"client_name":"My Example Client",
		"logo_uri":"https://client.example.org/logo.png",
		"claims_redirect_uri":[
			"https://client.example.org/callback3"
		],
		"scope": "openid email offline uma_authorization"
	}

### Step 5: Authorize client and retrieve refresh token
As a client, make a call to the <code>authorization_endpoint</code>.  Make sure as a client that it declares these 2 scopes - uma_authorization (which defines you as a client requesting access to resources) and offline_access (so that you can obtain a **refresh token** for future calls without needing the patient to repeat consent online.) Here is an example call (line breaks below are just for display convenience):

	GET /authorize?
	response_type=code
	&scope=openid%20profile%20email%20uma_authorization%20offline_access
	&client_id=some_client_id
	&client_secret=some_client_secret
	&state=af0ifjsldkj
	&redirect_uri=https%3A%2F%2Fclient.example.org%2Fcb HTTP/1.1
	Host: server.example.com

The patient is then directed to the login page of HIE of One Authorization Server where he/she verifies his/her identity.  If successful, the patient will then see a consent screen that verifies that this client will have access to  resources registered on his/her authorization server.  If the patient accepts, the client is authorized and the client is now registered to the authorization server.  From now on, the client is now the **registered client**.  ***It is the responsibility of the client to correctly assign the registered client information (client_id, client_secret, refresh_token, authorization server URI) to the correct identity (patient, requesting party).***

## How a client becomes a resource server

Becoming a resource server is exactly the same steps as above except for the following changes:
### Requisite condition:
1. Resource server software has **resources** that pertain to the patient

### Step 4: Client dynamically registers to the authorization server
Resource server makes a call to the <code>dynamic_client_endpoint</code> to [register itself](https://tools.ietf.org/html/draft-ietf-oauth-dyn-reg-30).  Resource server will need to store the <code>client_id</code> and <code>client_secret</code> (such as in a database) going forward.  It is important for the resource server to consider which redirect URIs to use as one of them will need to handle the <code>claims_redirect_uri</code> parameter listed [here](#step-3:-client-makes-a-call-to-the-authorization-server-with-the-permission-ticket-given-by-the-resource-server).  Being a resource server requires that you declare a scope of <code>uma_protection</code>.  Being a resource server also allows it to be client, so a scope declaration can include both <code>uma_authorization</code> and <code>uma_protection</code>.  Here is an example call (line breaks below are just for display convenience):

	POST /register HTTP/1.1
	Content-Type: application/json
	Accept: application/json
	Host: server.example.com

	{
		"redirect_uris":[
			"https://rs.example.org/callback",
			"https://rs.example.org/callback2"]
		"client_name":"My Example Resource Server",
		"logo_uri":"https://rs.example.org/logo.png",
		"claims_redirect_uri":[
			"https://rs.example.org/callback3"
		],
		"scope": "openid email offline uma_authorization uma_protection"
	}

### Step 5: Authorize client and retrieve refresh token
As a resource server, make a call to the <code>authorization_endpoint</code>.  Make sure as a resource server that it declares these 2 scopes - uma_protection (which defines you as a resource server requesting access to serve resources) and offline_access (so that you can obtain a **refresh token** for future calls without needing the patient to repeat consent online.) Here is an example call (line breaks below are just for display convenience):

	GET /authorize?
	response_type=code
	&scope=openid%20profile%20email%20uma_protection%20offline_access
	&client_id=some_client_id
	&client_secret=some_client_secret
	&state=af0ifjsldkj
	&redirect_uri=https%3A%2F%2Frs.example.org%2Fcb HTTP/1.1
	Host: server.example.com

The patient is then directed to the login page of HIE of One Authorization Server where he/she verifies his/her identity.  If successful, the patient will then see a consent screen that verifies that this resource server will have access to his data and will be able to serve resources and register them on his/her authorization server.  If the patient accepts, the client is authorized and the client is now registered to the authorization server.  From now on, the client is now the **registered resource server**.  ***It is the responsibility of the resource server to correctly assign the registered client information (client_id, client_secret, refresh_token, authorization server URI) to the correct identity (patient).***

## How a requesting party (such as a physician) obtains authorization to access a resource

### Requisite conditions:
1.  The **authorization server** has a associated **resource server** (such as a patient centric EHR that supports the [FHIR](http://wiki.hl7.org/index.php?title=FHIR) API like [NOSH ChartingSystem](https://github.com/shihjay2/nosh-cs))
2.  The **registered client** knows the [FHIR](http://wiki.hl7.org/index.php?title=FHIR) **resource endpoints** such as medication list, problem list, allergy list, encounters, immunizations, binaries) to access/edit these resources.
3.  The patient has created policies via the policy API that define which **requesting party** (identified by his/her e-mail address) has permissions to  access/edit his/her resources.

### Step 1: Physician uses the registered client to access the resource server
The client makes an initial call to the **resource endpoint**.

	GET /fhir/Patient/1 HTTP/1.1
	Host: rs.example.com

The **resource server** sends a response back with the URI of the **authorization server** (<code>as_uri</code> parameter in the <code>WWW-Authenticate</code> portion of the return header) and a **permission ticket** (<code>ticket</code> in JSON return).

### Step 2: Client obtains an authorization API token (AAT) from the authorization server
The client makes a call to the <code>token_endpoint</code> of the **authorizaion server** as such (line breaks below are just for display convenience):

	GET /token?grant_type=client_credentials
	&client_id=some_client_id
	&client_secret=some_client_secret
	&scope=uma_authorization HTTP/1.1
	Host: as.example.com

The **authorization API token** is <code>access_token</code> in the JSON return which will be used in the <code>rpt_endpoint</code>.

### Step 3: Client makes a call to the authorization server with the permission ticket given by the resource server
The client makes a call to the <code>requesting_party_claims_endpoint</code> of the **authorization server** and presents the **permission ticket** as such (line breaks below are just for display convenience):

	GET /rqp_claims?client_id=some_client_id
	&state=abc<br>&ticket=016f84e8-f9b9-11e0-bd6f-0021cc6004de
	&claims_redirect_uri=https%3A%2F%2Fclient%2Eexample%2Ecom%2Fredirect_claims HTTP/1.1
	Host: as.example.com

The requesting party will then be directed following this call to the authorization server which will determine the identity of the requesting party where a login screen will be presented.  4 choices of login will exist (going in order of most likely to least)
1.  Login with [MDNosh Gateway](https://noshchartingsystem.com/mdnosh_gateway) (a federated physician single-sign-on solution).
2.  Login with [Google](https://myaccount.google.com/)
3.  Login with [Twitter](https://twitter.com/login)
4.  Login with the authorization server (if the physician is a registered user for the authorization server, which is the least likely scenario)

After login, the authorization server checks the login identity email address to the **claim** associated with the resource policy.  If there is a match, the requesting party is redirected by the client supplied redirect URL (<code>claims_redirect_uri</code> in the above example) with the added <code>authorization_state</code> parameter as such (line breaks below are just for display convenience)

	GET /redirect_claims?&state=abc
	&authorization_state=claims_submitted HTTP/1.1
	Host: client.example.com

### Step 4: Client makes a call to the authorization server to get a requesting party token
Client makes a call to the <code>rpt_endpoint</code> of the **authorization server** supplying the **authorization API token** and the **permission ticket** as such (line breaks below are just for display convenience):

	POST /authz_request HTTP/1.1
	Host: as.example.com
	Authorization: Bearer some_authorization_API_token
	...
	{
		"ticket": "some_permission_ticket"
	}

The **requesting party token** is <code>rpt</code> in the JSON return.

### Step 5: Client re-accesses resource server
The client redirects back to the **resource server** with the **requesting party token** attached to the original FHIR **resource endpoint**.

	GET /fhir/Patient/1 HTTP/1.1
	Host: rs.example.com
	Authorization: Bearer some_requesting party_token

The **resource server** makes calls to the **authorization server** to validate the **requesting party token** through an introspection call.  If validated, the resource server then presents the requested FHIR resource to the requesting party.




## Security Vulnerabilities

If you discover a security vulnerability within HIE of One Authorization Server, please send an e-mail to Michael Chen at shihjay2 at gmail.com. All security vulnerabilities will be promptly addressed.

## License

The HIE of One Authorization Server is open-sourced software licensed under the [GNU AGPLv3 license](https://opensource.org/licenses/AGPL-3.0).
