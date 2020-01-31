<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">

	<title>Trustee Authorization Server</title>

	<!-- Styles -->
	<link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/css/font-awesome.min.css') }}">
	<!-- <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}"> -->
	{{-- <link href="{{ elixir('css/app.css') }}" rel="stylesheet"> --}}
	@yield('view.stylesheet')
	<style>
		@import url(https://fonts.googleapis.com/css?family=Nunito);
		body {
			font-family: 'Nunito';
		}
		.fa-btn {
			margin-right: 6px;
		}

		/* Custom, iPhone Retina */
		@media only screen and (min-width : 320px) {
			.as_h2_1 {
				margin-left: 5px;
			}
			.as_h2_2 {
				margin-left: 10px;
			}
			.as_h2_3 {
				margin-left: 15px;
			}
			.as_h2_4 {
				margin-left: 20px;
			}
		}

		/* Extra Small Devices, Phones */
		@media only screen and (min-width : 480px) {
			.as_h2_1 {
				margin-left: 5px;
			}
			.as_h2_2 {
				margin-left: 10px;
			}
			.as_h2_3 {
				margin-left: 15px;
			}
			.as_h2_4 {
				margin-left: 20px;
			}
		}

		/* Small Devices, Tablets */
		@media only screen and (min-width : 768px) {
			.as_h2_1 {
				margin-left: 50px;
			}
			.as_h2_2 {
				margin-left: 100px;
			}
			.as_h2_3 {
				margin-left: 150px;
			}
			.as_h2_4 {
				margin-left: 200px;
			}
		}

		/* Medium Devices, Desktops */
		@media only screen and (min-width : 992px) {
			.as_h2_1 {
				margin-left: 50px;
			}
			.as_h2_2 {
				margin-left: 100px;
			}
			.as_h2_3 {
				margin-left: 150px;
			}
			.as_h2_4 {
				margin-left: 200px;
			}
		}

		/* Large Devices, Wide Screens */
		@media only screen and (min-width : 1200px) {
			.as_h2_1 {
				margin-left: 50px;
			}
			.as_h2_2 {
				margin-left: 100px;
			}
			.as_h2_3 {
				margin-left: 150px;
			}
			.as_h2_4 {
				margin-left: 200px;
			}
		}
	</style>
</head>
<body id="app-layout">
	<nav class="navbar navbar-default navbar-static-top">
		<div class="container">
			<div class="navbar-header">

				<!-- Collapsed Hamburger -->
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#app-navbar-collapse">
					<span class="sr-only">Toggle Navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>

				<!-- Branding Image -->
				<a class="navbar-brand" href="{{ url('/') }}">
					Trustee Authorization Server
					@if (isset($name))
						for {{ $name }}
					@endif
				</a>
			</div>

			<div class="collapse navbar-collapse" id="app-navbar-collapse">
				<!-- Left Side Of Navbar -->
				<ul class="nav navbar-nav">
					@if (!Auth::guest())
						@if (Session::get('is_owner') == 'yes')
							<!-- <li><a href="{{ url('/consent_table') }}">Consent Table</a></li> -->
							<li><a href="{{ url('/resource_servers') }}">My Resources</a></li>
							<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">My Policies <span class="caret"></span></a>
								<ul class="dropdown-menu" role="menu">
									<li><a href="{{ url('/default_policies') }}">Default Policies</a></li>
									<li><a href="{{ url('/custom_policies') }}">Custom Policies</a></li>
								</ul>
							</li>
							<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">My Users <span class="caret"></span></a>
								<ul class="dropdown-menu" role="menu">
									<li><a href="{{ url('/users') }}">Users Authorized</a></li>
									<li><a href="{{ url('/authorize_user') }}">Users Pending Authorization</a></li>
									@if (Session::get('invite') == 'yes')
										<li><a href="{{ url('/make_invitation') }}">Invite a User</a></li>
									@endif
									<li><a href="{{ url('/clients') }}">Clients Authorized</a></li>
									<li><a href="{{ url('/authorize_client') }}">Clients Pending Authorization</a></li>
								</ul>
							</li>
						@endif
					@endif
				</ul>

				<!-- Right Side Of Navbar -->
				<ul class="nav navbar-nav navbar-right">
					<!-- Authentication Links -->
					@if (Auth::guest())
						@if (!isset($noheader))
							<li><a href="{{ url('/login') }}">Login</a></li>
						@endif
					@else
						<li class="dropdown">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
								{{ Session::get('full_name') }} <span class="caret"></span>
							</a>

							<ul class="dropdown-menu" role="menu">
								<li><a href="{{ url('/my_info') }}"><i class="fa fa-btn fa-user"></i>My Information</a></li>
								<li><a href="{{ url('/change_password') }}"><i class="fa fa-btn fa-cog"></i>Change Password</a></li>
								@if (Session::get('is_owner') == 'yes')
									<li><a href="{{ url('/directories') }}"><i class="fa fa-btn fa-sitemap"></i>Directories</a></li>
									<li><a href="{{ url('/certifiers') }}"><i class="fa fa-btn fa-thumbs-o-up"></i>Certifiers</a></li>
									@if (Session::get('domain_url') !== 'hieofone.org' || Session::get('domain_url') !== 'trustee.ai' || env('DOCKER') == '0')
										<li><a href="{{ url('/setup_mail') }}"><i class="fa fa-btn fa-envelope"></i>E-mail Service</a></li>
									@endif
									<li><a href="{{ url('/activity_logs') }}"><i class="fa fa-btn fa-list-alt"></i>Activity Logs</a></li>
									@if (env('DOCKER') !== '1')
										<li><a href="{{ url('/update_system') }}"><i class="fa fa-btn fa-download"></i>Update System</a></li>
									@else
									    <li><a href="{{ url('/syncthing') }}"><i class="fa fa-btn fa-exchange"></i>Backups</a></li>
									@endif
								@endif
								<li><a href="{{ url('/logout') }}"><i class="fa fa-btn fa-sign-out"></i>Logout</a></li>
							</ul>
						</li>
					@endif
				</ul>
			</div>
		</div>
	</nav>

	@yield('content')

	<!-- JavaScripts -->
	<script src="{{ asset('assets/js/jquery-3.4.1.min.js') }}"></script>
	<script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
	<script src="{{ asset('assets/js/jquery.maskedinput.min.js') }}"></script>
	{{-- <script src="{{ elixir('js/app.js') }}"></script> --}}
	@yield('view.scripts')
	<script type="text/javascript">
		// var check_demo = false;
		// setInterval(function() {
		// 	$.ajax({
		// 		type: "GET",
		// 		url: "check_demo_self",
		// 		beforeSend: function(request) {
		// 			return request.setRequestHeader("X-CSRF-Token", $("meta[name='csrf-token']").attr('content'));
		// 		},
		// 		success: function(data){
		// 			if (data !== 'OK') {
		// 				if (check_demo === false) {
		// 					alert(data);
		// 					check_demo = true;
		// 				}
		// 			}
		// 		}
		// 	});
		// }, 3000);
	</script>
</body>
</html>
