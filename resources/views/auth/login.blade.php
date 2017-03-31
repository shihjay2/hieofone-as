@extends('layouts.app')

@section('view.stylesheet')
	<link rel="stylesheet" href="{{ asset('assets/css/toastr.min.css') }}">
@endsection

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">Login</div>
				<div class="panel-body">
					<div style="text-align: center;">
						<div style="text-align: center;">
							<i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
							@if ($errors->has('tryagain'))
								<div class="form-group has-error">
									<span class="help-block has-error">
										<strong>{{ $errors->first('tryagain') }}</strong>
									</span>
								</div>
							@endif
						</div>
					</div>
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/login') }}">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('username') ? ' has-error' : '' }}">
							<label for="username" class="col-md-4 control-label">Username </label>

							<div class="col-md-6">
								<input id="username" class="form-control" name="username" value="{{ old('username') }}" data-toggle="tooltip" title="Demo Username: AlicePatient">

								@if ($errors->has('username'))
									<span class="help-block">
										<strong>{{ $errors->first('username') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
							<label for="password" class="col-md-4 control-label">Password</label>

							<div class="col-md-6">
								<input id="password" type="password" class="form-control" name="password" data-toggle="tooltip" title="Demo Password: demo">

								@if ($errors->has('password'))
									<span class="help-block">
										<strong>{{ $errors->first('password') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<div class="checkbox">
									<label>
										<input type="checkbox" name="remember"> Remember Me
									</label>
								</div>
							</div>
						</div>

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-sign-in"></i> Login
								</button>

								<a class="btn btn-link" href="{{ url('/password_email') }}">Forgot Your Password?</a>
							</div>
						</div>
						@if (!isset($nooauth))
						<div class="form-group">
							<div class="col-md-6 col-md-offset-3">
								<button type="button" class="btn btn-primary btn-block" id="connectUportBtn" onclick="loginBtnClick()">Login with uPort</button>
								<a class="btn btn-primary btn-block" href="{{ url('/mdnosh') }}">
									<i class="fa fa-btn fa-openid"></i> Login with mdNOSH
								</a>
								@if (isset($google))
									<a class="btn btn-primary btn-block" href="{{ url('/google') }}">
										<i class="fa fa-btn fa-google"></i> Login with Google
									</a>
								@endif
								@if (isset($twitter))
									<a class="btn btn-primary btn-block" href="{{ url('/twitter') }}">
										<i class="fa fa-btn fa-twitter"></i> Login with Twitter
									</a>
								@endif
							</div>
						</div>
						@endif
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('view.scripts')
<script src="{{ asset('assets/js/web3.js') }}"></script>
<script src="{{ asset('assets/js/uport-connect.js') }}"></script>
<script src="{{ asset('assets/js/toastr.min.js') }}"></script>
<!-- <script src="{{ asset('assets/js/uport-connect-core.js') }}"></script> -->
<!-- <script src="{{ asset('assets/js/uportlib.js') }}"></script> -->
<!-- <script src="{{ asset('assets/js/aes.js') }}"></script> -->
<!-- <script src="{{ asset('assets/js/aes-json-format.js') }}"></script> -->
<!-- <script src="{{ asset('assets/js/uport-lib/dist/uportlib.js') }}"></script> -->
<!-- <script src="{{ asset('assets/js/uport-lib/node_modules/web3/dist/web3.js') }}"></script> -->
<script type="text/javascript">
	$(document).ready(function() {
		$("#username").focus();
		$('[data-toggle="tooltip"]').tooltip();
	});
	// Setup
	const Connect = window.uportconnect.Connect;
	const appName = 'hieofone';
	const connect = new Connect(appName);
	const web3 = connect.getWeb3();
	const loginBtnClick = () => {
		connect.requestCredentials().then((credentials) => {
			console.log(credentials);
			var uport_url = '<?php echo route("login_uport"); ?>';
			var home = '<?php echo route("home"); ?>';
			$.ajax({
				type: "POST",
				url: uport_url,
				data: 'name=' + credentials.name + '&uport=' + credentials.address,
				beforeSend: function(request) {
					return request.setRequestHeader("X-CSRF-Token", $("meta[name='csrf-token']").attr('content'));
				},
				success: function(data){
					if (data !== 'OK') {
						toastr.error(data);
						// console.log(data);
					} else {
						window.location = home;
					}
				}
			});
			// render();
		}, console.err);
	};

	let globalState = {
		uportId: "",
		txHash: "",
		sendToAddr: "0x687422eea2cb73b5d3e242ba5456b782919afc85",
		sendToVal: "5"
	};

	const uportConnect = function () {
		web3.eth.getCoinbase((error, address) => {
			if (error) { throw error; }
			console.log(address);
			globalState.uportId = address;
		});
	};

	const sendEther = () => {
		const value = parseFloat(globalState.sendToVal) * 1.0e18;
		const gasPrice = 100000000000;
		const gas = 500000;
		web3.eth.sendTransaction(
			{
				from: globalState.uportId,
				to: globalState.sendToAddr,
				value: value,
				gasPrice: gasPrice,
				gas: gas
			},
			(error, txHash) => {
				if (error) { throw error; }
				globalState.txHash = txHash;
				console.log(txHash);
			}
		);
	};
	// let appName = 'hieofone';
	// let rpcUrl = "https://consensysnet.infura.io:8545";
    // let Uport = window.uportlib.Uport;
    // let web3 = new Web3();
	//
    // let options = {
    //     ipfsProvider: {
    //       host: 'ipfs.infura.io',
    //       port: '5001',
    //       protocol: 'https',
    //       root: ''
    //     }
    // };
    // let uport = new Uport(appName, options);
    // let uportProvider = uport.getUportProvider(rpcUrl);
    // web3.setProvider(uportProvider);
    // const loginBtnClick = function () {
    //     web3.eth.getCoinbase((error, address) => {
    //     	if (error) { throw error; }
    //     	web3.eth.defaultAccount = address;
	// 		uport.getUserPersona().then((userPersona) => {
	//             let profile = userPersona.getProfile();
	// 			let claims = userPersona.getAllClaims();
	//             console.log(profile);
	// 			console.log(claims);
	//             let name = profile.name.split(' ');
	// 			let json_claims = JSON.stringify(claims);
	// 			$('<form action="login_uport" method="POST">' +
	// 		    '<input type="hidden" name="uport" value="uport">' +
	// 			'<input type="hidden" name="firstname" value="' + name[0] + '">' +
	// 			'<input type="hidden" name="lastname" value="' + name[1] + '">' +
	// 			'<input type="hidden" name="claims" value="' + json_claims + '">' +
	// 		    '</form>').submit();
				// $('input[name="password"]').val('demodemo');
	            // $('input[name="username"]').val(firstname[0]);
	            // $("#loginform").submit();
	// 		});
	// 	});
    // };

</script>
@endsection
