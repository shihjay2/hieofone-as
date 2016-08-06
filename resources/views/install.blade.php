@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">Setup your HIE of One Authorization Server</div>
				<div class="panel-body">
					<div style="text-align: center;">
					  <i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
					</div>
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/install') }}">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('username') ? ' has-error' : '' }}">
							<label for="username" class="col-md-4 control-label">Username</label>

							<div class="col-md-6">
								<input id="username" type="username" class="form-control" name="username" value="{{ old('username') }}">

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
								<input id="password" type="password" class="form-control" name="password">

								@if ($errors->has('password'))
									<span class="help-block">
										<strong>{{ $errors->first('password') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('confirm_password') ? ' has-error' : '' }}">
							<label for="confirm_password" class="col-md-4 control-label">Confirm Password</label>

							<div class="col-md-6">
								<input id="confirm_password" type="password" class="form-control" name="confirm_password">

								@if ($errors->has('confirm_password'))
									<span class="help-block">
										<strong>{{ $errors->first('confirm_password') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('first_name') ? ' has-error' : '' }}">
							<label for="first_name" class="col-md-4 control-label">First Name</label>

							<div class="col-md-6">
								<input id="first_name" class="form-control" name="first_name" value="{{ old('first_name') }}">

								@if ($errors->has('first_name'))
									<span class="help-block">
										<strong>{{ $errors->first('first_name') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('last_name') ? ' has-error' : '' }}">
							<label for="last_name" class="col-md-4 control-label">Last Name</label>

							<div class="col-md-6">
								<input id="last_name" class="form-control" name="last_name" value="{{ old('last_name') }}">

								@if ($errors->has('last_name'))
									<span class="help-block">
										<strong>{{ $errors->first('last_name') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('date_of_birth') ? ' has-error' : '' }}">
							<label for="date_of_birth" class="col-md-4 control-label">Date of Birth</label>

							<div class="col-md-6">
								<input id="date_of_birth" type="date" class="form-control" name="date_of_birth" value="{{ old('date_of_birth') }}">

								@if ($errors->has('date_of_birth'))
									<span class="help-block">
										<strong>{{ $errors->first('date_of_birth') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
							<label for="email" class="col-md-4 control-label">E-Mail Address</label>

							<div class="col-md-6">
								<input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}">

								@if ($errors->has('email'))
									<span class="help-block">
										<strong>{{ $errors->first('email') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('mobile') ? ' has-error' : '' }}">
							<label for="mobile" class="col-md-4 control-label">Mobile (for SMS)</label>

							<div class="col-md-6">
								<input id="mobile" type="tel" class="form-control" name="mobile" value="{{ old('mobile') }}">

								@if ($errors->has('mobile'))
									<span class="help-block">
										<strong>{{ $errors->first('mobile') }}</strong>
									</span>
								@endif
							</div>
						</div>



						<div class="panel panel-default">
							<div class="panel-heading">Google Account (for email and authetication)</div>
							<div class="panel-body">
								<div class="form-group{{ $errors->has('google_client_id') ? ' has-error' : '' }}">
									<label for="google_client_id" class="col-md-4 control-label">Client ID</label>

									<div class="col-md-6">
										<input id="google_client_id" class="form-control" name="google_client_id" value="{{ old('google_client_id') }}">

										@if ($errors->has('google_client_id'))
											<span class="help-block">
												<strong>{{ $errors->first('google_client_id') }}</strong>
											</span>
										@endif
									</div>
								</div>
								<div class="form-group{{ $errors->has('google_client_secret') ? ' has-error' : '' }}">
									<label for="google_client_secret" class="col-md-4 control-label">Client Secret</label>

									<div class="col-md-6">
										<input id="google_client_secret" class="form-control" name="google_client_secret" value="{{ old('google_client_secret') }}">

										@if ($errors->has('google_client_secret'))
											<span class="help-block">
												<strong>{{ $errors->first('google_client_secret') }}</strong>
											</span>
										@endif
									</div>
								</div>
								<div class="form-group{{ $errors->has('smtp_username') ? ' has-error' : '' }}">
									<label for="smtp_username" class="col-md-4 control-label">E-mail Address for Google</label>

									<div class="col-md-6">
										<input id="smtp_username" type="email" class="form-control" name="smtp_username" value="{{ old('smtp_username') }}">

										@if ($errors->has('smtp_username'))
											<span class="help-block">
												<strong>{{ $errors->first('smtp_username') }}</strong>
											</span>
										@endif
									</div>
								</div>
							</div>
						</div>

						<div class="panel panel-default">
							<div class="panel-heading">Twitter Account (for authentication)</div>
							<div class="panel-body">
								<div class="form-group{{ $errors->has('twitter_client_id') ? ' has-error' : '' }}">
									<label for="twitter_client_id" class="col-md-4 control-label">Client ID</label>

									<div class="col-md-6">
										<input id="twitter_client_id" class="form-control" name="twitter_client_id" value="{{ old('twitter_client_id') }}">

										@if ($errors->has('twitter_client_id'))
											<span class="help-block">
												<strong>{{ $errors->first('twitter_client_id') }}</strong>
											</span>
										@endif
									</div>
								</div>
								<div class="form-group{{ $errors->has('twitter_client_secret') ? ' has-error' : '' }}">
									<label for="twitter_client_secret" class="col-md-4 control-label">Client Secret</label>

									<div class="col-md-6">
										<input id="twitter_client_secret" class="form-control" name="twitter_client_secret" value="{{ old('twitter_client_secret') }}">

										@if ($errors->has('twitter_client_secret'))
											<span class="help-block">
												<strong>{{ $errors->first('twitter_client_secret') }}</strong>
											</span>
										@endif
									</div>
								</div>
							</div>
						</div>

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-sign-in"></i> Install
								</button>
							</div>
						</div>
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
		$("#mobile").mask("(999) 999-9999");
	});
</script>
@endsection
