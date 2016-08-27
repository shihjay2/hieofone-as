@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">
					Edit My Information
				</div>
				<div class="panel-body">
					<div style="text-align: center;">
					  <i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
					</div>
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/my_info_edit') }}">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('first_name') ? ' has-error' : '' }}">
							<label for="first_name" class="col-md-4 control-label">First Name</label>

							<div class="col-md-6">
								<input id="first_name" class="form-control" name="first_name" value="{{ old('first_name', $first_name) }}">

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
								<input id="last_name" class="form-control" name="last_name" value="{{ old('last_name', $last_name) }}">

								@if ($errors->has('last_name'))
									<span class="help-block">
										<strong>{{ $errors->first('last_name') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
							<label for="email" class="col-md-4 control-label">E-Mail Address</label>

							<div class="col-md-6">
								<input id="email" type="email" class="form-control" name="email" value="{{ old('email', $email) }}">

								@if ($errors->has('email'))
									<span class="help-block">
										<strong>{{ $errors->first('email') }}</strong>
									</span>
								@endif
							</div>
						</div>

						@if (isset($date_of_birth))
							<div class="form-group{{ $errors->has('date_of_birth') ? ' has-error' : '' }}">
								<label for="date_of_birth" class="col-md-4 control-label">Date of Birth</label>

								<div class="col-md-6">
									<input id="date_of_birth" type="date" class="form-control" name="date_of_birth" value="{{ old('date_of_birth', $date_of_birth) }}">

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
									<input id="mobile" class="form-control" name="mobile" value="{{ old('mobile', $mobile) }}">

									@if ($errors->has('mobile'))
										<span class="help-block">
											<strong>{{ $errors->first('mobile') }}</strong>
										</span>
									@endif
								</div>
							</div>
						@endif

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-sign-in"></i> Update
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
		$("#first_name").focus();
		$("#mobile").mask("(999) 999-9999");
	});
</script>
@endsection
