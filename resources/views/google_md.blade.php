@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">Additional information to access {{ $owner }}</div>
				<div class="panel-body">
					{!! $npi !!}
					<form class="form-horizontal" role="form" method="POST" action="{{ url('/google_md') }}">
						{{ csrf_field() }}
						<div style="text-align: center;">
							<h4>Enter your NPI number below if you don't see one that matches in the list.</h4>
						</div>
						<br>
						<div class="form-group{{ $errors->has('npi') ? ' has-error' : '' }}">
							<label for="npi" class="col-md-4 control-label">NPI (National Provider Identifier)</label>

							<div class="col-md-6">
								<input id="npi" class="form-control" name="npi" value="{{ old('npi') }}">

								@if ($errors->has('npi'))
									<span class="help-block">
										<strong>{{ $errors->first('npi') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-primary">
									<i class="fa fa-btn fa-check"></i> Proceed
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
		$("#npi").focus();
	});
</script>
@endsection
