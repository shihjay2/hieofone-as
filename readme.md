# HIE of One Authorization Server

HIE of One Authorization Server is a simple User Managed Access ([UMA](https://tools.ietf.org/html/draft-hardjono-oauth-umacore-14)) Server that incorporates [OAuth2](https://tools.ietf.org/html/rfc6749) and [OpenID Connect](https://openid.net/connect/) protocols to facilitate the ability of an individual to control and authorize access to his or her health information to clients such as physicians, hospitals, caregivers, and third-party applications.

## Features
OAuth2 OpenID Connect compliant server
OAuth2 OpenID Connect relying party for Google and Twitter
User Managed Access compliant authentication server

## How a client registers with the HIE of One Authorization Server

### Requisite conditions:
1. Patient has registered a domain name where the HIE of One Authorization Server is installed (ie domain.xyz)
2. Client software (such as an EHR with a patient portal) has the capability to make HTTPS calls (such as CURL) and is able to process JSON responses.

### Step 1: Announce patient's HIE of One email address and authorization server to the client
Patient submits his or her HIE of One email address (patient@domain.xyz) to the client (like a physician), where domain.xyz is the same domain name as where the HIE of One **authorization server** is installed.  This can be done, for example, through a EHR patient portal that has a text input that allows processing of the patient's HIE of One email address.  Alternatively, the provider would enter this email address in the EHR directly when the patient's chart is open.  A negative result results in a 404 error response.

### Step 2: Client verifies patient's authorization server
Client submits email address to the following address per [Webfinger protocol](https://tools.ietf.org/html/rfc7033) to confirm validity of the account and email address: https://domain.xyz/.well-known/webfinger?resource=acct:patient@domain.xyz&rel=http://openid.net/specs/connect/1.0/issuer

### Step 3: Client learns authorization server endpoints
Client takes  the JSON return (specifically the <code>href subkey</code> under <code>links</code>) and when appending <code>.well-known/uma-configuration</code> to the URL, like this: https://domain.xyz/uma/.well-known/uma-configuration, client will see in JSON format all the valid UMA endpoints where client makes calls to initiate a token request, start authorization, request a permission ticket, and make requesting party claims.

### Step 4: Client dynamically registers to the authorization server
Client makes a call to the <code>dynamic_client_endpoint</code> to [register itself](https://tools.ietf.org/html/draft-ietf-oauth-dyn-reg-30).  Client will need to store the <code>client_id</code> and <code>client_secret</code> (such as in a database) going forward.  It is important for the client to consider which redirect URIs to use as one of them will need to handle the <code>claims_redirect_uri</code> parameter listed [here](#step-3:-client-makes-a-call-to-the-authorization-server-with-the-permission-ticket-given-by-the-resource-server)

### Step 5: Patient is notified about the new client and authorizes the client
HIE of One authorization server will send an email and/or text to the patient that a new client is dynamically registered (but not authorized yet).  Patient verifies that this is indeed a valid client.  The patient presses Authorize and the client is now registered to the authorization server.  From now on, the client is now the **registered client**


## How a requesting party (such as a physician) obtains authorization to access a resource

### Requisite conditions:
1.  The **authorization server** has a associated **resource server** (such as a patient centric EHR that supports the [FHIR](http://wiki.hl7.org/index.php?title=FHIR) API like [NOSH ChartingSystem](https://github.com/shihjay2/nosh-cs))
2.  The **registered client** knows the [FHIR](http://wiki.hl7.org/index.php?title=FHIR) **resource endpoints** such as medication list, problem list, allergy list, encounters, immunizations, binaries) to access/edit these resources.
3.  The patient has created policies via the policy API that define which **requesting party** (identified by his/her e-mail address) has permissions to  access/edit his/her resources.

### Step 1: Physician uses the registered client to access the resource server
The client makes an initial call to the **resource endpoint**.

<code>
GET /fhir/Patient/1 HTTP/1.1<br>
Host: rs.example.com
</code>

The **resource server** sends a response back with the URI of the **authorization server** (<code>as_uri</code> parameter in the <code>WWW-Authenticate</code> portion of the return header) and a **permission ticket** (<code>ticket</code> in JSON return).

### Step 2: Client obtains an authorization API token (AAT) from the authorization server
The client makes a call to the <code>token_endpoint</code> of the **authorizaion server** as such (line breaks below are just for display convenience):

<code>
GET /token?grant_type=client_credentials<br>
&client_id=some_client_id<br>
&client_secret=some_client_secret<br>
&scope=uma_authorization HTTP/1.1<br>
Host: as.example.com
</code>

The **authorization API token** is <code>access_token</code> in the JSON return which will be used in the <code>rpt_endpoint</code>.

### Step 3: Client makes a call to the authorization server with the permission ticket given by the resource server
The client makes a call to the <code>requesting_party_claims_endpoint</code> of the **authorization server** and presents the **permission ticket** as such (line breaks below are just for display convenience):

<code>GET /rqp_claims?client_id=some_client_id<br>
&state=abc<br>&ticket=016f84e8-f9b9-11e0-bd6f-0021cc6004de<br>
&claims_redirect_uri=https%3A%2F%2Fclient%2Eexample%2Ecom%2Fredirect_claims HTTP/1.1<br>
Host: as.example.com
</code>

The requesting party will then be directed following this call to the authorization server which will determine the identity of the requesting party where a login screen will be presented.  4 choices of login will exist (going in order of most likely to least)
1.  Login with [MDNosh Gateway](https://noshchartingsystem.com/mdnosh_gateway) (a federated physician single-sign-on solution).
2.  Login with [Google](https://myaccount.google.com/)
3.  Login with [Twitter](https://twitter.com/login)
4.  Login with the authorization server (if the physician is a registered user for the authorization server, which is the least likely scenario)

After login, the authorization server checks the login identity email address to the **claim** associated with the resource policy.  If there is a match, the requesting party is redirected by the client supplied redirect URL (<code>claims_redirect_uri</code> in the above example) with the added <code>authorization_state</code> parameter as such (line breaks below are just for display convenience)

<code>
GET /redirect_claims?&state=abc<br>
&authorization_state=claims_submitted HTTP/1.1<br>
Host: client.example.com
</code>

### Step 3: Client makes a call to the authorization server to get a requesting party token
Client makes a call to the <code>rpt_endpoint</code> of the **authorization server** supplying the **authorization API token** and the **permission ticket** as such (line breaks below are just for display convenience):

<code>
POST /authz_request HTTP/1.1<br>
Host: as.example.com<br>
Authorization: Bearer some_authorization_API_token<br>
...<br>
{
 "ticket": "some_permission_ticket"
}
</code>

The **requesting party token** is <code>rpt</code> in the JSON return.

### Step 4: Client re-accesses resource server
The client redirects back to the **resource server** with the **requesting party token** attached to the original FHIR **resource endpoint**.

<code>
GET /fhir/Patient/1 HTTP/1.1<br>
Host: rs.example.com<br>
Authorization: Bearer some_requesting party_token
</code>

The **resource server** makes calls to the **authorization server** to validate the **requesting party token** through an introspection call.  If validated, the resource server then presents the requested FHIR resource to the requesting party.


## Security Vulnerabilities

If you discover a security vulnerability within HIE of One Authorization Server, please send an e-mail to Michael Chen at shihjay2 at gmail.com. All security vulnerabilities will be promptly addressed.

## License

The HIE of One Authorization Server is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
