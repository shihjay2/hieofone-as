@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">Add a directory</div>
				<div class="panel-body">
					<div style="text-align: center;">
						<i class="fa fa-sitemap fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i>
					</div>
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/directory_add') }}">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('uri') ? ' has-error' : '' }}">
							<label for="uri" class="col-md-4 control-label">URL of Directory</label>

							<div class="col-md-6">
								<input id="uri" type="username" class="form-control" name="uri" value="{{ old('uri') }}">

								@if ($errors->has('uri'))
									<span class="help-block">
										<strong>{{ $errors->first('uri') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-sign-in"></i> Save
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
