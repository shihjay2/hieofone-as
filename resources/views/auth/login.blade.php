@extends('layouts.app')

@section('view.stylesheet')
	<link rel="stylesheet" href="{{ asset('assets/css/toastr.min.css') }}">
	<style>
	html {
		position: relative;
		min-height: 100%;
	}
	body {
	/* Margin bottom by footer height */
		margin-bottom: 60px;
	}
	.footer {
		position: absolute;
		bottom: 0;
		width: 100%;
		/* Set the fixed height of the footer here */
		height: 60px;
		background-color: #f5f5f5;
	}
	.container .text-muted {
		margin: 20px 0;
	}
	.footer > .container {
		padding-right: 15px;
		padding-left: 15px;
	}
	</style>
@endsection

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h4 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">Login</h4>
				</div>
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
						<div id="uport_indicator" style="text-align: center;display:none;">
							<i class="fa fa-spinner fa-spin fa-pulse fa-2x fa-fw"></i><span id="modaltext" style="margin:10px">Loading uPort...</span><br><br>
						</div>
					</div>
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/login') }}" style="display:none">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('username') ? ' has-error' : '' }}">
							<label for="username" class="col-md-4 control-label">Username </label>

							<div class="col-md-6">
								<input id="username" class="form-control" name="username" value="{{ old('username') }}" data-toggle="tooltip" title="{{ $demo_username }}">

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
								<input id="password" type="password" class="form-control" name="password" data-toggle="tooltip" title="{{ $demo_password }}">

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
							<div class="col-md-8 col-md-offset-2">
								<button type="button" class="btn btn-primary btn-block" id="connectUportBtn" onclick="loginBtnClick()">
									<img src="{{ asset('assets/uport-logo-white.svg') }}" height="25" width="25" style="margin-right:5px"></img> Login with uPort
								</button>
								<button type="button" class="btn btn-primary btn-block" id="connectUportBtn1"><i class="fa fa-btn fa-plus"></i> Add Doximity Clinician Verification</button>
								<!-- <button type="button" class="btn btn-primary btn-block" id="connectUportBtn1" onclick="uportConnect()">Connect uPort</button> -->
								<!-- <button type="button" class="btn btn-primary btn-block" id="connectUportBtn2" onclick="sendEther()">Send Ether</button> -->
								@if (isset($google))
									<a class="btn btn-primary btn-block" href="{{ url('/google') }}">
										<i class="fa fa-btn fa-google"></i> Login with Google
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
<footer class="footer">
	<div class="container">
		<p class="text-muted pull-right">Version git-{{ $version }}</p>
	</div>
</footer>
<div class="modal" id="modal1" role="dialog">
	<div class="modal-dialog">
	  <!-- Modal content-->
		<div class="modal-content">
			<div id="modal1_header" class="modal-header">Add NPI credential to uPort?</div>
			<div id="modal1_body" class="modal-body" style="height:30vh;overflow-y:auto;">
				<p>This will simulate adding a verified credential to your existing uPort.</p>
				<p>Clicking on Get from Doximity will demonstrate how you can get a verified credential if you have an existing Doximity account</p>
				<p>After the NPI credential is added, click on Login with uPort</p>
				<p>This will enable you to write a prescription.</p>
			</div>
			<div class="modal-footer">
				<a href="https://dir.hieofone.org/doximity_start/" target="_blank" class="btn btn-default" id="doximity_modal"><i class="fa fa-btn fa-hand-o-right"></i> Get from Doximity</a>
				<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-btn fa-times"></i> Close</button>
			  </div>
		</div>
	</div>
</div>
@endsection

@section('view.scripts')
<!-- <script src="{{ asset('assets/js/web3.js') }}"></script> -->
<script src="{{ asset('assets/js/uport-connect.js') }}"></script>
<script src="{{ asset('assets/js/toastr.min.js') }}"></script>
<script type="text/javascript">
	$(document).ready(function() {
		$("#username").focus();
		$('[data-toggle="tooltip"]').tooltip();
		$("#connectUportBtn1").click(function(){
            $('#modal1').modal('show');
        });
		$('#doximity_modal').click(function(){
			$('#modal1').modal('hide');
		});
	});
	// Setup
	const Connect = window.uportconnect;
	const appName = 'Trustee for <?php echo $name; ?>';
	const uport = new Connect(appName, {
		network: 'rinkeby'
	});
	const loginBtnClick = () => {
		$('#uport_indicator').show();
		uport.requestDisclosure({
			requested: ['name', 'email', 'NPI'],
			notifications: true // We want this if we want to recieve credentials
	    });
		uport.onResponse('disclosureReq').then((res) => {
			var did = res.payload.did;
			var credentials = res.payload.verified;
			console.log(res.payload);
			var uport_url = '<?php echo route("login_uport"); ?>';
			var uport_data = 'name=' + res.payload.name + '&uport=' + res.payload.did;
			if (typeof res.payload.NPI !== 'undefined') {
				uport_data += '&npi=' + res.payload.NPI;
			}
			if (typeof res.payload.email !== 'undefined') {
				uport_data += '&email=' + res.payload.email;
			}
			$.ajax({
				type: "POST",
				url: uport_url,
				data: uport_data,
				dataType: 'json',
				beforeSend: function(request) {
					return request.setRequestHeader("X-CSRF-Token", $("meta[name='csrf-token']").attr('content'));
				},
				success: function(data){
					if (data.message !== 'OK') {
						toastr.error(data.message);
					} else {
						window.location = data.url;
					}
				}
			});
		}, console.err);
	};
</script>
@endsection
