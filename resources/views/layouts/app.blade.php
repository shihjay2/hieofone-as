<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">

	<title>HIE of One Authorization Server</title>

	<!-- Styles -->
	<link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/css/font-awesome.min.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/css/main.css') }}">
	{{-- <link href="{{ elixir('css/app.css') }}" rel="stylesheet"> --}}
	@yield('view.stylesheet')
	<style>
		body {
			font-family: 'Lato';
		}
		.fa-btn {
			margin-right: 6px;
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
					HIE of One Authorization Server
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
							<li><a href="{{ url('/home') }}">My Resources</a></li>
							<li><a href="{{ url('/default_policies') }}">My Policies</a></li>
							<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">My Clients <span class="caret"></span></a>
								<ul class="dropdown-menu" role="menu">
									<li><a href="{{ url('/clients') }}">Authorized</a></li>
									<li><a href="{{ url('/authorize_client') }}">Pending Authorization</a></li>
								</ul>
							</li>
							<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">My Users <span class="caret"></span></a>
								<ul class="dropdown-menu" role="menu">
									<li><a href="{{ url('/users') }}">Authorized</a></li>
									<li><a href="{{ url('/authorize_user') }}">Pending Authorization</a></li>
								</ul>
							</li>
							@if (Session::get('invite') == 'yes')
								<li><a href="{{ url('/make_invitation') }}">Invite</a></li>
							@endif
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
								<li><a href="{{ url('/my_info') }}"><i class="fa fa-btn fa-cogs"></i>My Information</a></li>
								<li><a href="{{ url('/change_password') }}"><i class="fa fa-btn fa-cog"></i>Change Password</a></li>
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
	<script src="{{ asset('assets/js/jquery-3.1.1.min.js') }}"></script>
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
