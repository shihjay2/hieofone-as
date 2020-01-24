@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h4 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">Add a Trustee Mouse</h4>
				</div>
				<div class="panel-body">
					<div style="text-align: center;">
						<i class="fa fa-sitemap fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
					</div>
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/syncthing') }}">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('deviceID') ? ' has-error' : '' }}">
							<label for="deviceID" class="col-md-4 control-label">DeviceID of Trustee Mouse or Syncthing Node</label>

							<div class="col-md-6">
								<input id="deviceID" class="form-control" name="deviceID" value="{{ old('deviceID', $deviceID) }}" required>

								@if ($errors->has('deviceID'))
									<span class="help-block">
										<strong>{{ $errors->first('deviceID') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
							<label for="name" class="col-md-4 control-label">Name of Trustee Mouse of Syncthing Node</label>

							<div class="col-md-6">
								<input id="name" class="form-control" name="name" value="{{ old('name', $name) }}">

								@if ($errors->has('name'))
									<span class="help-block">
										<strong>{{ $errors->first('name') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-sign-in"></i> Add
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
		$("#uri").focus();
	});
</script>
@endsection
