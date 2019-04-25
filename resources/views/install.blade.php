@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				@if (isset($pnosh))
					<div class="panel-heading">Set up your Trustee Authorization Server and Patient Centered EHR (NOSH)</div>
				@else
					<div class="panel-heading">Set up your Trustee Authorization Server</div>
				@endif
				<div class="panel-body">
					<div style="text-align: center;">
					  <i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
					</div>
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/install') }}">
						{{ csrf_field() }}
						<div class="alert alert-success">
							You can choose any username you want as long as nobody else is using it already in the Directory.<br>
							Your email address will not be shown in the Directory.
						</div>
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

						<div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
							<label for="email" class="col-md-4 control-label">E-Mail Address</label>

							<div class="col-md-6">
								<input id="email" type="email" class="form-control" name="email" value="{{ old('email', $email_default) }}">

								@if ($errors->has('email'))
									<span class="help-block">
										<strong>{{ $errors->first('email') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<hr/>
						<div class="alert alert-success">
							Any information set below will not be shown or stored in the Directory.<br>
							@if (isset($pnosh))
								It is only used to initialize your Patient Centered EHR (NOSH).<br>
							@endif
							<a href="https://dir.hieofone.org/privacy_policy" target="_blank">Click here to view the Privacy Policy for the Directory</a>
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

						@if (isset($pnosh))
							<div class="form-group{{ $errors->has('gender') ? ' has-error' : '' }}">
								<label for="gender" class="col-md-4 control-label">Gender</label>

								<div class="col-md-6">
									<select class="form-control" id="gender" name="gender" value="{{ old('gender') }}">
										<option value="m">Male</option>
										<option value="f">Female</option>
										<option value="u">Undifferentiated</option>
									</select>

									@if ($errors->has('gender'))
										<span class="help-block">
											<strong>{{ $errors->first('gender') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="form-group{{ $errors->has('address') ? ' has-error' : '' }}">
								<label for="address" class="col-md-4 control-label">Street Address</label>

								<div class="col-md-6">
									<input id="address" class="form-control" name="address" value="{{ old('address') }}">

									@if ($errors->has('address'))
										<span class="help-block">
											<strong>{{ $errors->first('address') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="form-group{{ $errors->has('city') ? ' has-error' : '' }}">
								<label for="city" class="col-md-4 control-label">City</label>

								<div class="col-md-6">
									<input id="city" class="form-control" name="city" value="{{ old('city') }}">

									@if ($errors->has('city'))
										<span class="help-block">
											<strong>{{ $errors->first('city') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="form-group{{ $errors->has('state') ? ' has-error' : '' }}">
								<label for="state" class="col-md-4 control-label">State</label>

								<div class="col-md-6">
									<select class="form-control" id="state" name="state" value="{{ old('state') }}">
										<option value="" selected="selected"></option>
										<option value="AL">Alabama</option>
										<option value="AK">Alaska</option>
										<option value="AS">America Samoa</option>
										<option value="AZ">Arizona</option>
										<option value="AR">Arkansas</option>
										<option value="CA">California</option>
										<option value="CO">Colorado</option>
										<option value="CT">Connecticut</option>
										<option value="DE">Delaware</option>
										<option value="DC">District of Columbia</option>
										<option value="FM">Federated States of Micronesia</option>
										<option value="FL">Florida</option>
										<option value="GA">Georgia</option>
										<option value="GU">Guam</option>
										<option value="HI">Hawaii</option>
										<option value="ID">Idaho</option>
										<option value="IL">Illinois</option>
										<option value="IN">Indiana</option>
										<option value="IA">Iowa</option>
										<option value="KS">Kansas</option>
										<option value="KY">Kentucky</option>
										<option value="LA">Louisiana</option>
										<option value="ME">Maine</option>
										<option value="MH">Marshall Islands</option>
										<option value="MD">Maryland</option>
										<option value="MA">Massachusetts</option>
										<option value="MI">Michigan</option>
										<option value="MN">Minnesota</option>
										<option value="MS">Mississippi</option>
										<option value="MO">Missouri</option>
										<option value="MT">Montana</option>
										<option value="NE">Nebraska</option>
										<option value="NV">Nevada</option>
										<option value="NH">New Hampshire</option>
										<option value="NJ">New Jersey</option>
										<option value="NM">New Mexico</option>
										<option value="NY">New York</option>
										<option value="NC">North Carolina</option>
										<option value="ND">North Dakota</option>
										<option value="OH">Ohio</option>
										<option value="OK">Oklahoma</option>
										<option value="OR">Oregon</option>
										<option value="PW">Palau</option>
										<option value="PA">Pennsylvania</option>
										<option value="PR">Puerto Rico</option>
										<option value="RI">Rhode Island</option>
										<option value="SC">South Carolina</option>
										<option value="SD">South Dakota</option>
										<option value="TN">Tennessee</option>
										<option value="TX">Texas</option>
										<option value="UT">Utah</option>
										<option value="VT">Vermont</option>
										<option value="VI">Virgin Island</option>
										<option value="VA">Virginia</option>
										<option value="WA">Washington</option>
										<option value="WV">West Virginia</option>
										<option value="WI">Wisconsin</option>
										<option value="WY">Wyoming</option>
									</select>

									@if ($errors->has('state'))
										<span class="help-block">
											<strong>{{ $errors->first('state') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="form-group{{ $errors->has('zip') ? ' has-error' : '' }}">
								<label for="zip" class="col-md-4 control-label">Zip</label>

								<div class="col-md-6">
									<input id="zip" class="form-control" name="zip" value="{{ old('zip') }}">

									@if ($errors->has('zip'))
										<span class="help-block">
											<strong>{{ $errors->first('zip') }}</strong>
										</span>
									@endif
								</div>
							</div>
						@endif



						<!-- <div class="panel panel-default">
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
						</div> -->
						<div class="alert alert-success">
							When you click Setup, you will then be registering your Patient Centered EHR and then to the Directory.
						</div>
						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-sign-in"></i> Setup
								</button>
								<a href="https://dir.hieofone.org" target="_blank" class="btn btn-danger">
									<i class="fa fa-btn fa-times"></i> Cancel
								</a>
								<a href="https://dir.hieofone.org/support" target="_blank" class="btn btn-warning">
									<i class="fa fa-btn fa-question"></i> Contact Support
								</a>
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
<script src="{{ asset('assets/js/pwstrength-bootstrap.min.js') }}"></script>
<script type="text/javascript">
	$(document).ready(function() {
		$("#username").focus();
		$(":password").pwstrength();
		$("#mobile").mask("(999) 999-9999");
	});
</script>
@endsection
