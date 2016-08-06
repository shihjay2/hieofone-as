@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">Change Password</div>
				<div class="panel-body">
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
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/change_password') }}">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('old_password') ? ' has-error' : '' }}">
							<label for="old_password" class="col-md-4 control-label">Old Password</label>

							<div class="col-md-6">
								<input id="old_password" type="password" class="form-control" name="old_password">

								@if ($errors->has('old_password'))
									<span class="help-block">
										<strong>{{ $errors->first('old_password') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
							<label for="password" class="col-md-4 control-label">New Password</label>

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
							<label for="confirm_password" class="col-md-4 control-label">Confirm New Password</label>

							<div class="col-md-6">
								<input id="confirm_password" type="password" class="form-control" name="confirm_password">

								@if ($errors->has('confirm_password'))
									<span class="help-block">
										<strong>{{ $errors->first('confirm_password') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-sign-in"></i> Change Password
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
		$("#old_password").focus();
	});
</script>
@endsection
