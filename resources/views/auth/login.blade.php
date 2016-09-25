@extends('layouts.app')

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
								<div class="form-group  has-error">
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
								<a class="btn btn-primary btn-block" href="{{ url('/mdnosh') }}">
									<i class="fa fa-btn fa-openid"></i> Login with mdNOSH Gateway
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
<script type="text/javascript">
	$(document).ready(function() {
		$("#username").focus();
		$('[data-toggle="tooltip"]').tooltip();
	});
</script>
@endsection
